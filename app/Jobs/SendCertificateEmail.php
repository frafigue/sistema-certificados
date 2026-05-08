<?php

namespace App\Jobs;

use App\Mail\CertificateSent;
use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Middleware\RateLimited;

use Throwable;

class SendCertificateEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(public Certificate $certificate) {}

     public function middleware()
    {
        return [new RateLimited('emails')];
    }
    
    public function backoff()
    {
        return [10, 30, 60];
    }

    public function handle()
    {
        $certificate = null;
        try {
            Log::info('INICIANDO ENVIO DE CERTIFICADO ID: ' . $this->certificate->id);
            // 🔥 SIEMPRE traer fresco desde DB
            $certificate = Certificate::with('person')->find($this->certificate->id);
            if (!$certificate) {
                Log::error('Certificado no encontrado');
                return;
            }
            if (!$certificate->person) {
                Log::error('Persona no encontrada');
                return;
            }
            if (empty($certificate->person->email)) {
                Log::error('Email vacío para persona ID: ' . $certificate->person->id);
                // 🔴 Marcar como error
                $certificate->update([
                    'email_status' => 'error',
                    'email_error'  => 'Email vacío'
                ]);

                return;
            }

            Log::info('Enviando a: ' . $certificate->person->email);

            Mail::to($certificate->person->email)
                ->send(new CertificateSent($certificate));

            Log::info('EMAIL ENVIADO OK');

            // ✅ ACTUALIZAR (FORMA SEGURA)
            $certificate->update([
                'email_status'   => 'enviado',
                'email_sent_at' => now(),
                'email_error'   => null,
            ]);

            Log::info('ESTADO ACTUALIZADO EN BD');

        } catch (Throwable $e) {

            Log::error('ERROR REAL: ' . $e->getMessage());

            if ($certificate) {
                $certificate->update([
                    'email_status' => 'error',
                    'email_error'  => $e->getMessage(),
                ]);
            }
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error(
            'Fallo definitivo al enviar email del certificado ID '
            . $this->certificate->id . ': ' . $e->getMessage()
        );
    }
}