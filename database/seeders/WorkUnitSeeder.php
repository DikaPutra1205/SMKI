<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Database\Seeder;

class WorkUnitSeeder extends Seeder
{
    public function run(): void
    {
        // Root units
        $biroHukum = WorkUnit::create(['nama' => 'Biro Hukum', 'parent_id' => null]);
        $biroSDM = WorkUnit::create(['nama' => 'Biro SDM', 'parent_id' => null]);
        $biroTI = WorkUnit::create(['nama' => 'Biro Teknologi Informasi', 'parent_id' => null]);
        $biroKeuangan = WorkUnit::create(['nama' => 'Biro Keuangan', 'parent_id' => null]);

        // Sub-units
        WorkUnit::create(['nama' => 'Sub-Bagian Perundangan', 'parent_id' => $biroHukum->id]);
        WorkUnit::create(['nama' => 'Sub-Bagian Bantuan Hukum', 'parent_id' => $biroHukum->id]);
        WorkUnit::create(['nama' => 'Sub-Bagian Kepegawaian', 'parent_id' => $biroSDM->id]);
        WorkUnit::create(['nama' => 'Sub-Bagian Pengembangan SDM', 'parent_id' => $biroSDM->id]);
        WorkUnit::create(['nama' => 'Sub-Bagian Infrastruktur', 'parent_id' => $biroTI->id]);
        WorkUnit::create(['nama' => 'Sub-Bagian Aplikasi', 'parent_id' => $biroTI->id]);

        // Assign PIC ke Biro TI
        User::where('email', 'pic@smki.test')->update(['unit_id' => $biroTI->id]);

        $this->command->info('Berhasil menyemai unit kerja dan sub-unit.');
    }
}
