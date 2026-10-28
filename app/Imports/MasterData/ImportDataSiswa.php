<?php

namespace App\Imports\MasterData;

use App\Models\mst_kelas;
use App\Models\mst_thn_aka;
use App\Models\scctcust;
use App\Support\SchoolScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ImportDataSiswa implements WithMultipleSheets, ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public function sheets(): array
    {
        return [
            0 => $this,
        ];
    }

    public function collection(Collection $collection): void
    {
        $cacheKey = 'import_data_siswa';
        $requiredKeys = ['nama', 'unit', 'kelas', 'kelompok', 'angkatan'];
        $parsedRows = [];

        foreach ($collection as $row) {
            if ($row->filter()->isEmpty()) {
                continue;
            }

            $rowData = $row->toArray();
            if (count(array_intersect_key(array_flip($requiredKeys), $rowData)) !== count($requiredKeys)) {
                continue;
            }

            $rowData['unit'] = trim((string) ($rowData['unit'] ?? ''));
            $rowData['kelas'] = is_numeric($rowData['kelas'] ?? null)
                ? (string) (int) $rowData['kelas']
                : trim((string) ($rowData['kelas'] ?? ''));
            $rowData['kelompok'] = trim((string) ($rowData['kelompok'] ?? ''));

            $nis = isset($rowData['nis']) ? trim((string) $rowData['nis']) : '';
            $nodaftar = isset($rowData['nodaftar']) ? trim((string) $rowData['nodaftar']) : '';
            $rowData['nis'] = $nis !== '' ? $nis : null;
            $rowData['nodaftar'] = $nodaftar !== '' ? $nodaftar : null;
            $rowData['ortu'] = trim((string) ($rowData['ortu'] ?? $rowData['genus'] ?? $rowData['ayah'] ?? '')) ?: null;
            $rowData['gender'] = SchoolScope::parseGender(
                $rowData['gender'] ?? $rowData['jenis_kelamin'] ?? $rowData['jk'] ?? null
            );

            $parsedRows[] = $rowData;
        }

        if (empty($parsedRows)) {
            Cache::forget($cacheKey);
            return;
        }

        $nisList = array_values(array_filter(array_column($parsedRows, 'nis')));
        $nodaftarList = array_values(array_filter(array_column($parsedRows, 'nodaftar')));

        $existingNis = $nisList !== []
            ? scctcust::whereIn('NOCUST', $nisList)->pluck('NOCUST')->flip()->all()
            : [];
        $existingNodaftar = $nodaftarList !== []
            ? scctcust::whereIn('NUM2ND', $nodaftarList)->pluck('NUM2ND')->flip()->all()
            : [];

        $kelasMaster = mst_kelas::all();
        $thnAkaSet = array_flip(
            mst_thn_aka::pluck('thn_aka')
                ->map(fn ($v) => trim((string) $v))
                ->filter(fn ($v) => $v !== '')
                ->all()
        );

        $processedData = [];

        foreach ($parsedRows as $rowData) {
            $rowData['status'] = 1;
            $status_ket = null;

            if (!$rowData['nis'] && !$rowData['nodaftar']) {
                $rowData['status'] = 0;
                $status_ket = 'NIS &/ NODAFTAR tidak boleh kosong';
            }

            if ($rowData['nis'] && !is_numeric($rowData['nis'])) {
                $rowData['status'] = 0;
                $status_ket = $this->appendKet($status_ket, 'NIS harus berupa angka');
            } elseif ($rowData['nis']) {
                $rowData['nis'] = (string) $rowData['nis'];
                if (isset($existingNis[$rowData['nis']])) {
                    $rowData['status'] = 2;
                    $status_ket = $this->appendKet(
                        $status_ket,
                        "Siswa dengan NIS {$rowData['nis']} sudah ada, data akan diupdate"
                    );
                }
            }

            if ($rowData['nodaftar'] && !is_numeric($rowData['nodaftar'])) {
                $rowData['status'] = 0;
                $status_ket = $this->appendKet($status_ket, 'NODAFTAR harus berupa angka');
            } elseif ($rowData['nodaftar']) {
                $rowData['nodaftar'] = (string) $rowData['nodaftar'];
                if (isset($existingNodaftar[$rowData['nodaftar']])) {
                    $rowData['status'] = 2;
                    $status_ket = $this->appendKet(
                        $status_ket,
                        "Siswa dengan nodaftar {$rowData['nodaftar']} sudah ada, data akan diupdate"
                    );
                }
            }

            $matchedKelas = mst_kelas::matchFromCollection(
                $kelasMaster,
                $rowData['unit'],
                $rowData['kelas'],
                $rowData['kelompok'],
            );

            if (!$matchedKelas) {
                $rowData['status'] = 0;
                $status_ket = $this->appendKet(
                    $status_ket,
                    sprintf(
                        'Kelas tidak ditemukan (Unit: %s, Kelas: %s, Kelompok: %s). Sesuaikan dengan Master Kelas.',
                        $rowData['unit'],
                        $rowData['kelas'],
                        $rowData['kelompok'],
                    )
                );
            }

            if (empty($rowData['gender'])) {
                $rowData['status'] = 0;
                $status_ket = $this->appendKet(
                    $status_ket,
                    'Gender wajib diisi (L/P atau Laki-laki/Perempuan)'
                );
            }

            $angkatan = trim((string) ($rowData['angkatan'] ?? ''));
            if ($angkatan === '' || !isset($thnAkaSet[$angkatan])) {
                $rowData['status'] = 0;
                $status_ket = $this->appendKet(
                    $status_ket,
                    sprintf(
                        'Angkatan tidak ditemukan (%s). Buat dulu di Tahun Akademik.',
                        $angkatan
                    )
                );
            }

            $rowData['keterangan'] = $status_ket;
            $processedData[] = $rowData;
        }

        Cache::put($cacheKey, $processedData, now()->addMinutes(60));
    }

    private function appendKet(?string $current, string $message): string
    {
        if ($current === null || $current === '') {
            return $message;
        }

        return $current . ', ' . $message;
    }

    public function headingRow(): int
    {
        return 1;
    }
}
