<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Database\Seeder;

class WorkUnitSeeder extends Seeder
{
    public function run(): void
    {
        $bbRoot = WorkUnit::firstOrCreate(
            ['nama' => 'BALAI BESAR PELATIHAN SDM KOMUNIKASI DAN DIGITAL'],
            ['parent_id' => null]
        );

        $bpsdmRoot = WorkUnit::firstOrCreate(
            ['nama' => 'BALAI PELATIHAN SDM KOMUNIKASI DAN DIGITAL'],
            ['parent_id' => null]
        );

        $talentaRoot = WorkUnit::firstOrCreate(
            ['nama' => 'BALAI PELATIHAN TALENTA KOMUNIKASI DAN DIGITAL'],
            ['parent_id' => null]
        );

        // BALAI BESAR children
        WorkUnit::firstOrCreate(['nama' => 'BBP SDM Komdigi Medan'], ['parent_id' => $bbRoot->id]);
        WorkUnit::firstOrCreate(['nama' => 'BBP SDM Komdigi Makassar'], ['parent_id' => $bbRoot->id]);

        // BALAI PELATIHAN SDM children
        WorkUnit::firstOrCreate(['nama' => 'BPSDM Komdigi Jakarta'], ['parent_id' => $bpsdmRoot->id]);
        WorkUnit::firstOrCreate(['nama' => 'BPSDM Komdigi Bandung'], ['parent_id' => $bpsdmRoot->id]);
        WorkUnit::firstOrCreate(['nama' => 'BPSDM Komdigi Surabaya'], ['parent_id' => $bpsdmRoot->id]);
        WorkUnit::firstOrCreate(['nama' => 'BPSDM Komdigi Yogyakarta'], ['parent_id' => $bpsdmRoot->id]);
        WorkUnit::firstOrCreate(['nama' => 'BPSDM Komdigi Banjarmasin'], ['parent_id' => $bpsdmRoot->id]);
        WorkUnit::firstOrCreate(['nama' => 'BPSDM Komdigi Manado'], ['parent_id' => $bpsdmRoot->id]);

        // BALAI TALENTA child
        WorkUnit::firstOrCreate(['nama' => 'Balai Pelatihan Talenta Komdigi'], ['parent_id' => $talentaRoot->id]);

        // Assign PIC ke BALAI BESAR
        User::where('email', 'pic@smki.test')->update(['unit_id' => $bbRoot->id]);

        $this->command->info('Berhasil menyemai unit kerja Komdigi.');
    }
}
