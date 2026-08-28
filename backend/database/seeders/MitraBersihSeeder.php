<?php

namespace Database\Seeders;

use App\Models\MitraProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Akun mitra BisaBersih untuk lingkungan pengembangan.
 *
 * Yang di-seed hanya IDENTITAS — nama dan akun. Semua angka performa sengaja
 * nol: rating 0, ulasan 0, dan tidak ada satu pun tugas selesai. Level mereka
 * karena itu ikut terhitung di tingkat terendah, bukan diisi "Gold/Silver"
 * seperti katalog contoh yang lama.
 *
 * Konsekuensinya halaman menampilkan tanda "-" untuk bintang, level, dan
 * jumlah order sampai ada customer yang benar-benar memberi ulasan. Begitu
 * ulasan pertama masuk, LevelCleaner menaikkan levelnya sendiri.
 *
 * Seeder ini aman dijalankan berulang: dicocokkan lewat email.
 */
class MitraBersihSeeder extends Seeder
{
    /**
     * Nama dan gender adalah identitas akun uji — bukan data performa.
     * Gender disimpan sebagai data karena menebaknya dari nama sering salah.
     */
    private const MITRA = [
        ['nama' => 'Siti Aminah', 'email' => 'siti.aminah@mitra.test', 'phone' => '081300000001', 'gender' => 'wanita'],
        ['nama' => 'Rina Wulandari', 'email' => 'rina.wulandari@mitra.test', 'phone' => '081300000002', 'gender' => 'wanita'],
        ['nama' => 'Dewi Lestari', 'email' => 'dewi.lestari@mitra.test', 'phone' => '081300000003', 'gender' => 'wanita'],
        ['nama' => 'Agus Setiawan', 'email' => 'agus.setiawan@mitra.test', 'phone' => '081300000004', 'gender' => 'pria'],
        ['nama' => 'Budi Hartono', 'email' => 'budi.hartono@mitra.test', 'phone' => '081300000005', 'gender' => 'pria'],
        ['nama' => 'Nur Fadilah', 'email' => 'nur.fadilah@mitra.test', 'phone' => '081300000006', 'gender' => 'wanita'],
    ];

    public function run(): void
    {
        foreach (self::MITRA as $i => $m) {
            $user = User::firstOrNew(['email' => $m['email']]);
            $user->name = $m['nama'];
            $user->phone = $m['phone'];

            if (! $user->exists) {
                $user->password = Hash::make('password123');
            }

            // Kolom sensitif tidak ada di $fillable — ditetapkan eksplisit.
            $user->role = 'mitra';
            $user->status_verifikasi = 'verified';
            $user->is_active = true;
            $user->save();

            MitraProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'no_ktp' => '3271'.str_pad((string) ($i + 1), 12, '0', STR_PAD_LEFT),
                    'foto_ktp' => 'seed/ktp-placeholder.jpg',
                    'gender' => $m['gender'],
                    // Nol apa adanya: belum ada yang menilai mereka.
                    'rating_avg' => 0,
                    'rating_count' => 0,
                ],
            );
        }

        $this->command?->info(count(self::MITRA).' mitra BisaBersih siap (rating & order masih nol).');
    }
}
