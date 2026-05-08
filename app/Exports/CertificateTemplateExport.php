<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CertificateTemplateExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return collect([]);
    }

    /**
     * @return array
     */
    // app/Exports/CertificateTemplateExport.php

    public function headings(): array
    {
        return [
            'dni',
            'nombre',
            'apellido',
            'curso',
            'nota',
            'unidad_academica',
            'area',
            'subarea',
            'codigo_incremental',
            'anio',
            'tipo_certificado',
            'iniciales',
            'dni_3',
            'cuv'
        ];
    }
}
