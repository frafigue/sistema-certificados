<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Person;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use iio\libmergepdf\Merger;

class CertificateService
{
    const PDF_W = 1122;
    const PDF_H = 793;

    private function pdfOptions(): array
    {
        return [
            'dpi'                     => 96,
            'defaultFont'             => 'sans-serif',
            'isRemoteEnabled'         => false,
            'isHtml5ParserEnabled'    => true,
            'isFontSubsettingEnabled' => true,
        ];
    }

    public function generate(array $data): Certificate
    {
        $person = Person::findOrFail($data['person_id']);
        $course = Course::with(['area', 'responsables', 'resolution'])
            ->findOrFail($data['course_id']);

        // 🔒 Evitar duplicados
        if (Certificate::where('unique_code', $data['cuv'])->exists()) {
            return Certificate::where('unique_code', $data['cuv'])->first();
        }

        // ===============================
        // QR
        // ===============================
        $qrPath = 'qrcodes/' . $data['cuv'] . '.svg';

        Storage::disk('public')->makeDirectory('qrcodes');

        QrCode::format('svg')->size(150)->generate(
            route('certificates.verify', $data['cuv']),
            storage_path('app/public/' . $qrPath)
        );

        // ===============================
        // DATA PARA PDF
        // ===============================
        $bladeData = [
            'person'          => $person,
            'course'          => $course,
            'qr_path'         => storage_path('app/public/' . $qrPath),
            'certificateData' => [
                'cuv'              => $data['cuv'],
                'tipo_certificado' => $data['tipo_certificado'],
                'condition'        => $data['tipo_certificado'],
                'ano'              => $data['ano'] ?? date('Y'),
            ],
        ];

        $templateFront = $course->area->template_front;
        $templateBack  = $course->area->template_back ?? null;

        $designFront = $this->extractDesignFromTemplate($templateFront);

        if ($designFront) {
            $htmlFront = Blade::render(
                $this->buildHtmlFromDesign($designFront),
                $bladeData
            );
        } else {
            $htmlFront = Blade::render($templateFront, $bladeData);
        }

        $htmlBack = null;

        if (!empty($templateBack)) {
            $designBack = $this->extractDesignFromTemplate($templateBack);

            if ($designBack) {
                $htmlBack = Blade::render(
                    $this->buildHtmlFromDesign($designBack),
                    $bladeData
                );
            } else {
                $htmlBack = Blade::render($templateBack, $bladeData);
            }
        }

        // ===============================
        // PDF
        // ===============================
        $tempPath = storage_path('app/temp_pdf');
        File::ensureDirectoryExists($tempPath);

        $frontFile = $tempPath . '/' . $data['cuv'] . '_front.pdf';
        Pdf::loadHTML($htmlFront)
            ->setPaper([0, 0, 793, 1122])
            ->setOptions($this->pdfOptions())
            ->save($frontFile);

        if ($htmlBack) {
            $backFile = $tempPath . '/' . $data['cuv'] . '_back.pdf';
            Pdf::loadHTML($htmlBack)
                ->setPaper([0, 0, 793, 1122])
                ->setOptions($this->pdfOptions())
                ->save($backFile);

            $merger = new Merger;
            $merger->addFile($frontFile);
            $merger->addFile($backFile);

            $finalPdf = $merger->merge();

            File::delete($frontFile, $backFile);
        } else {
            $finalPdf = File::get($frontFile);
            File::delete($frontFile);
        }

        $pdfPath = 'certificates/' . $data['cuv'] . '.pdf';
        Storage::disk('public')->makeDirectory('certificates');
        Storage::disk('public')->put($pdfPath, $finalPdf);

        if (!Storage::disk('public')->exists($pdfPath)) {
            throw new \Exception('El PDF no se guardó correctamente: ' . $pdfPath);
        }

        unset($htmlFront, $htmlBack, $bladeData, $finalPdf);
        gc_collect_cycles();

        // ===============================
        // BD
        // ===============================
        return Certificate::create([
            'import_history_id'        => $data['import_history_id'] ?? null,
            'course_id'                => $course->id,
            'person_id'                => $person->id,
            'condition'                => $data['tipo_certificado'],
            'nota'                     => $data['nota'] ?? null,
            'unique_code'              => $data['cuv'],
            'qr_path'                  => $qrPath,
            'pdf_path'                 => $pdfPath,
            'unidad_academica'         => $data['unidad_academica'] ?? null,
            'area_excel'               => $data['area'] ?? null,
            'subarea'                  => $data['subarea'] ?? null,
            'codigo_incremental'       => $data['codigo_incremental'] ?? null,
            'anio'                     => $data['ano'] ?? date('Y'),
            'tipo_certificado'         => $this->deriveConditionCode($data['tipo_certificado']),
            'iniciales'                => $data['iniciales'] ?? null,
            'tres_ultimos_digitos_dni' => $data['3_ultimos_del_dni'] ?? null,
        ]);
    }

    private function deriveConditionCode(string $condition): string
    {
        $lettersOnly = preg_replace('/[^a-zA-Z]/', '', $condition);
        return strtoupper(substr($lettersOnly, 0, 3));
    }

    private function extractDesignFromTemplate(?string $html): ?array
    {
            if (!$html) return null;
            if (preg_match('/<!-- DESIGN_JSON:(.*?):END_DESIGN_JSON -->/s', $html, $matches)) {
                $json = html_entity_decode($matches[1], ENT_QUOTES);
                return json_decode($json, true);
            }
            return null;
    }

    private function urlToLocalPath(string $url): ?string
    {
        if (preg_match('#/storage/(.+)$#', $url, $m)) {
            $localPath = Storage::disk('public')->path($m[1]);
            $localPath = str_replace('/', DIRECTORY_SEPARATOR, $localPath);
            if (file_exists($localPath)) {
                return $localPath;
            }
        }
        return null;
    }

    private function imageToBase64(string $path): ?string
    {
        if (!file_exists($path)) return null;

        $mime = mime_content_type($path);

        // Redimensionar y comprimir si es PNG o JPEG
        if (in_array($mime, ['image/png', 'image/jpeg', 'image/jpg'])) {
            $img = imagecreatefromstring(file_get_contents($path));
            if ($img) {
                // Redimensionar a máximo 1122x793 manteniendo proporción
                $origW = imagesx($img);
                $origH = imagesy($img);
                $maxW  = 1122;
                $maxH  = 793;

                if ($origW > $maxW || $origH > $maxH) {
                    $ratio  = min($maxW / $origW, $maxH / $origH);
                    $newW   = (int)($origW * $ratio);
                    $newH   = (int)($origH * $ratio);
                    $resized = imagecreatetruecolor($newW, $newH);
                    imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
                    imagedestroy($img);
                    $img = $resized;
                }

                ob_start();
                if ($mime === 'image/png') {
                    imagepng($img, null, 6); // compresión 6/9
                } else {
                    imagejpeg($img, null, 75); // calidad 75%
                }
                $imageData = ob_get_clean();
                imagedestroy($img);

                return "data:{$mime};base64," . base64_encode($imageData);
            }
        }

        // Fallback para otros formatos
        return "data:{$mime};base64," . base64_encode(file_get_contents($path));
    }

    public function buildHtmlFromDesign(array $design): string
    {
        $bgImage  = $design['background'] ?? '';
        $elements = $design['elements']   ?? [];

        $pdfW = self::PDF_W;
        $pdfH = self::PDF_H;

        $scaleX = 1;
        $scaleY = 1;
        $scale  = 1;

        if ($bgImage && str_starts_with($bgImage, 'http')) {
            $localPath = $this->urlToLocalPath($bgImage);
            if ($localPath) {
                $bgImage = $this->imageToBase64($localPath) ?? '';
            } else {
                $bgImage = '';
            }
        }

        $bgStyle  = $bgImage ? '' : 'background-color: #ffffff;';
        $bgImgTag = $bgImage
            ? "<img src=\"{$bgImage}\" style=\"position:absolute;top:0;left:0;width:{$pdfW}px;height:{$pdfH}px;z-index:0;\" />"
            : '';

        $elementsHtml = '';

        foreach ($elements as $el) {
            $type = $el['type'] ?? '';

            $x = round(($el['x']      ?? 0)   * $scaleX);
            $y = round(($el['y']      ?? 0)   * $scaleY);
            $w = round(($el['width']  ?? 200) * $scaleX);
            $h = round(($el['height'] ?? 40)  * $scaleY);

            $fontSizeRaw = $el['fontSize'] ?? 14;
            $fontSize    = round($fontSizeRaw * $scale);

            $style = "position:absolute; left:{$x}px; top:{$y}px; width:{$w}px; height:{$h}px; z-index:1;";

            switch ($type) {
                case 'nombre':
                    $color  = $el['color']  ?? '#000000';
                    $bold   = ($el['bold']   ?? true)  ? 'font-weight:bold;'  : '';
                    $italic = ($el['italic'] ?? false) ? 'font-style:italic;' : '';
                    $align  = $el['align']  ?? 'center';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; {$bold}{$italic} text-align:{$align}; overflow:hidden;\">{{ \$person->nombre }} {{ \$person->apellido }}</div>";
                    break;

                case 'curso':
                    $color  = $el['color']  ?? '#000000';
                    $bold   = ($el['bold']   ?? false) ? 'font-weight:bold;'  : '';
                    $italic = ($el['italic'] ?? false) ? 'font-style:italic;' : '';
                    $align  = $el['align']  ?? 'center';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; {$bold}{$italic} text-align:{$align}; overflow:hidden;\">{{ \$course->nombre }}</div>";
                    break;

                case 'fecha':
                    $color = $el['color'] ?? '#000000';
                    $align = $el['align'] ?? 'center';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; text-align:{$align}; overflow:hidden;\">{{ date('d/m/Y') }}</div>";
                    break;

                case 'anio':
                    $color = $el['color'] ?? '#000000';
                    $align = $el['align'] ?? 'center';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; text-align:{$align}; overflow:hidden;\">{{ \$certificateData['ano'] ?? date('Y') }}</div>";
                    break;

                case 'condicion':
                    $color = $el['color'] ?? '#000000';
                    $bold  = ($el['bold'] ?? false) ? 'font-weight:bold;' : '';
                    $align = $el['align'] ?? 'center';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; {$bold} text-align:{$align}; overflow:hidden;\">{{ \$certificateData['condition'] ?? \$certificateData['tipo_de_certificado'] ?? \$certificateData['tipo_certificado'] ?? '' }}</div>";
                    break;

                case 'area':
                    $color = $el['color'] ?? '#000000';
                    $align = $el['align'] ?? 'center';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; text-align:{$align}; overflow:hidden;\">{{ \$course->area->nombre ?? '' }}</div>";
                    break;

                case 'horas':
                    $color = $el['color'] ?? '#000000';
                    $align = $el['align'] ?? 'center';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; text-align:{$align}; overflow:hidden;\">{{ \$course->horas ?? '' }} horas</div>";
                    break;

                case 'cuv':
                    $color = $el['color'] ?? '#666666';
                    $align = $el['align'] ?? 'center';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; text-align:{$align}; overflow:hidden;\">CUV: {{ \$certificateData['cuv'] ?? '' }}</div>";
                    break;

                case 'qr':
                    $elementsHtml .= "<img src=\"{{ \$qr_path }}\" style=\"{$style} object-fit:contain;\">";
                    break;

                case 'objetivo':
                    $color  = $el['color']  ?? '#000000';
                    $bold   = ($el['bold']   ?? false) ? 'font-weight:bold;'  : '';
                    $italic = ($el['italic'] ?? false) ? 'font-style:italic;' : '';
                    $align  = $el['align']  ?? 'left';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; {$bold}{$italic} text-align:{$align}; overflow:hidden; word-wrap:break-word;\"><strong>Objetivo:</strong> {{ \$course->objetivo ?? '' }}</div>";
                    break;

                case 'contenido':
                    $color  = $el['color']  ?? '#000000';
                    $bold   = ($el['bold']   ?? false) ? 'font-weight:bold;'  : '';
                    $italic = ($el['italic'] ?? false) ? 'font-style:italic;' : '';
                    $align  = $el['align']  ?? 'left';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; {$bold}{$italic} text-align:{$align}; overflow:hidden; word-wrap:break-word;\"><strong>Contenido:</strong> {{ \$course->contenido ?? '' }}</div>";
                    break;

                case 'firma_responsable':
                    $index          = (int)($el['firma_index'] ?? 0);
                    $color          = $el['color'] ?? '#000000';
                    $align          = $el['align'] ?? 'center';
                    $imgH           = (int)round($h * 0.55);
                    $fontSizeNombre = $fontSize;
                    $fontSizeCargo  = max(9, $fontSize - 2);

                    $elementsHtml .= "@php \$_resp = \$course->responsables->get({$index}); @endphp"
                        . "@if(\$_resp)"
                        . "<div style=\"{$style} text-align:{$align};\">"
                        . "@php"
                        . " \$_sigPath = \$_resp->signature_path"
                        . "     ? str_replace('/', DIRECTORY_SEPARATOR, Storage::disk('public')->path(\$_resp->signature_path))"
                        . "     : null;"
                        . "@endphp"
                        . "@if(\$_sigPath && file_exists(\$_sigPath))"
                        . "@php \$_sigData = 'data:' . mime_content_type(\$_sigPath) . ';base64,' . base64_encode(file_get_contents(\$_sigPath)); @endphp"
                        . "<img src=\"{{ \$_sigData }}\" style=\"width:100%; height:{$imgH}px; object-fit:contain; display:block;\">"
                        . "@else"
                        . "<div style=\"width:100%; height:{$imgH}px; border-bottom:1px solid {$color}; display:block;\"></div>"
                        . "@endif"
                        . "<div style=\"font-size:{$fontSizeNombre}px; color:{$color}; font-weight:bold; line-height:1.4; text-align:{$align};\">{{ \$_resp->nombre }}</div>"
                        . "<div style=\"font-size:{$fontSizeCargo}px; color:{$color}; line-height:1.3; text-align:{$align};\">{{ \$_resp->cargo ?? '' }}</div>"
                        . "</div>"
                        . "@endif";
                    break;

                case 'texto':
                    $color   = $el['color']   ?? '#000000';
                    $bold    = ($el['bold']    ?? false) ? 'font-weight:bold;'  : '';
                    $italic  = ($el['italic']  ?? false) ? 'font-style:italic;' : '';
                    $align   = $el['align']   ?? 'left';
                    $content = htmlspecialchars($el['content'] ?? 'Texto libre');
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; {$bold}{$italic} text-align:{$align}; overflow:hidden;\">{$content}</div>";
                    break;

                case 'imagen':
                    $src = $el['src'] ?? '';
                    if ($src) {
                        if (str_starts_with($src, 'http')) {
                            $localPath = $this->urlToLocalPath($src);
                            if ($localPath) {
                                $src = $this->imageToBase64($localPath) ?? '';
                            } else {
                                $src = '';
                            }
                        }
                        if ($src) {
                            $elementsHtml .= "<img src=\"{$src}\" style=\"{$style} object-fit:contain;\">";
                        }
                    }
                    break;
            }
        }

        $designJson = htmlspecialchars(json_encode($design), ENT_QUOTES);

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page { margin: 0; size: A4 landscape; }
        * { box-sizing: border-box; }
        html, body {
            margin: 0; padding: 0;
            width: {$pdfW}px; height: {$pdfH}px;
            overflow: hidden;
            font-family: 'DejaVu Sans', 'Helvetica', Arial, sans-serif;
        }
        .cert-page {
            position: relative;
            width: {$pdfW}px; height: {$pdfH}px;
            overflow: hidden;
        }
    </style>
</head>
<body>
<!-- DESIGN_JSON:{$designJson}:END_DESIGN_JSON -->
<div class="cert-page" style="{$bgStyle}">
{$bgImgTag}
{$elementsHtml}
</div>
</body>
</html>
HTML;
    }
}