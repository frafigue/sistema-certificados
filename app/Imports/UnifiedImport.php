<?php

namespace App\Imports;

use App\Imports\Sheets\PersonsSheetImport;
use App\Imports\Sheets\CertificatesSheetImport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class UnifiedImport implements WithMultipleSheets
{
    private PersonsSheetImport $personsSheet;
    private CertificatesSheetImport $certificatesSheet;

    public function __construct()
    {
        $this->personsSheet      = new PersonsSheetImport();
        $this->certificatesSheet = new CertificatesSheetImport();
    }

    public function sheets(): array
    {
        return [
            'Personas'     => $this->personsSheet,
            'Certificados' => $this->certificatesSheet,
            0              => $this->personsSheet,
            1              => $this->certificatesSheet,
        ];
    }

    public function getPersonsImportedCount(): int  { return $this->personsSheet->getImportedCount(); }
    public function getPersonsSkippedCount(): int   { return $this->personsSheet->getSkippedCount(); }
    public function getPersonsErrors(): array       { return $this->personsSheet->getErrors(); }
    public function getCertificatesImportedCount(): int { return $this->certificatesSheet->getImportedCount(); }
    public function getCertificatesErrors(): array  { return $this->certificatesSheet->getErrors(); }

    public function onImportCompleted(): void
    {
        $this->personsSheet->onImportCompleted();
        $this->certificatesSheet->processPendingCertificates();
    }

    public function forceFinalFlush(): void
    {
        $this->personsSheet->forceFinalFlush();
    }
}