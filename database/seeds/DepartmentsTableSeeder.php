<?php

use Illuminate\Database\Seeder;

class DepartmentsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('departments')->delete();
        
        \DB::table('departments')->insert(array (
            0 => 
            array (
                'id_department' => 1,
                'name_department' => 'Arsitektur',
            ),
            1 => 
            array (
                'id_department' => 2,
                'name_department' => 'BAPKM',
            ),
            2 => 
            array (
                'id_department' => 3,
                'name_department' => 'BEM FTSPK',
            ),
            3 => 
            array (
                'id_department' => 4,
                'name_department' => 'Biologi',
            ),
            4 => 
            array (
                'id_department' => 5,
                'name_department' => 'Biro Keuangan',
            ),
            5 => 
            array (
                'id_department' => 6,
                'name_department' => 'Biro Umum',
            ),
            6 => 
            array (
                'id_department' => 7,
                'name_department' => 'Departemen Teknologi Informasi',
            ),
            7 => 
            array (
                'id_department' => 8,
                'name_department' => 'Desain Interior',
            ),
            8 => 
            array (
                'id_department' => 9,
                'name_department' => 'Desain Komunikasi Visual',
            ),
            9 => 
            array (
                'id_department' => 10,
                'name_department' => 'Desain Produk',
            ),
            10 => 
            array (
                'id_department' => 11,
                'name_department' => 'Desain Produk Industri',
            ),
            11 => 
            array (
                'id_department' => 12,
                'name_department' => 'Dewan Profesor',
            ),
            12 => 
            array (
                'id_department' => 13,
                'name_department' => 'DIKST',
            ),
            13 => 
            array (
                'id_department' => 14,
                'name_department' => 'Direktorat Hubungan Internasional',
            ),
            14 => 
            array (
                'id_department' => 15,
                'name_department' => 'Direktorat Inovasi, Kerjasama, dan Kealumnian',
            ),
            15 => 
            array (
                'id_department' => 16,
                'name_department' => 'Direktorat Kemahasiswaan',
            ),
            16 => 
            array (
                'id_department' => 17,
                'name_department' => 'Direktorat Kemitraan Global',
            ),
            17 => 
            array (
                'id_department' => 18,
                'name_department' => 'Direktorat Pascasarjana dan Pengembangan Akademik',
            ),
            18 => 
            array (
                'id_department' => 19,
                'name_department' => 'Direktorat Pendidikan',
            ),
            19 => 
            array (
                'id_department' => 20,
                'name_department' => 'Direktorat Perencanaan Anggaran & Logistik',
            ),
            20 => 
            array (
                'id_department' => 21,
                'name_department' => 'Direktorat Sumber Daya Manusia dan Organisasi',
            ),
            21 => 
            array (
                'id_department' => 22,
                'name_department' => 'Dit PP Sarpras',
            ),
            22 => 
            array (
                'id_department' => 23,
                'name_department' => 'DPPSP',
            ),
            23 => 
            array (
                'id_department' => 24,
                'name_department' => 'DPTSI',
            ),
            24 => 
            array (
                'id_department' => 25,
                'name_department' => 'DRPM',
            ),
            25 => 
            array (
                'id_department' => 26,
                'name_department' => 'Fakultas Sains dan Analitika Data',
            ),
            26 => 
            array (
                'id_department' => 27,
                'name_department' => 'Fisika',
            ),
            27 => 
            array (
                'id_department' => 28,
                'name_department' => 'FTE',
            ),
            28 => 
            array (
                'id_department' => 29,
                'name_department' => 'HETI-ADB LOAN ITS',
            ),
            29 => 
            array (
                'id_department' => 30,
                'name_department' => 'IKOMA',
            ),
            30 => 
            array (
                'id_department' => 31,
                'name_department' => 'Informatika',
            ),
            31 => 
            array (
                'id_department' => 32,
                'name_department' => 'ITS Press',
            ),
            32 => 
            array (
                'id_department' => 33,
                'name_department' => 'Kantor Audit Internal',
            ),
            33 => 
            array (
                'id_department' => 34,
                'name_department' => 'Kimia',
            ),
            34 => 
            array (
                'id_department' => 35,
                'name_department' => 'KPM',
            ),
            35 => 
            array (
                'id_department' => 36,
                'name_department' => 'Lab Energi dan Lingkungan',
            ),
            36 => 
            array (
                'id_department' => 37,
                'name_department' => 'Lab Manajemen Konstruksi',
            ),
            37 => 
            array (
                'id_department' => 38,
                'name_department' => 'Lab Robotika',
            ),
            38 => 
            array (
                'id_department' => 39,
                'name_department' => 'Lab. RAMS Departemen Teknik Sistem Perkapalan',
            ),
            39 => 
            array (
                'id_department' => 40,
                'name_department' => 'Laboratorium Beton dan Bahan Bangunan',
            ),
            40 => 
            array (
                'id_department' => 41,
                'name_department' => 'Laboratorium Struktur Teknik Sipil',
            ),
            41 => 
            array (
                'id_department' => 42,
                'name_department' => 'Lembaga Pengelola Dana Abadi',
            ),
            42 => 
            array (
                'id_department' => 43,
                'name_department' => 'LPPM',
            ),
            43 => 
            array (
                'id_department' => 44,
                'name_department' => 'Manajemen Bisnis',
            ),
            44 => 
            array (
                'id_department' => 45,
                'name_department' => 'Matematika',
            ),
            45 => 
            array (
                'id_department' => 46,
                'name_department' => 'MMT',
            ),
            46 => 
            array (
                'id_department' => 47,
                'name_department' => 'MWA',
            ),
            47 => 
            array (
                'id_department' => 48,
                'name_department' => 'OWSD',
            ),
            48 => 
            array (
                'id_department' => 49,
                'name_department' => 'Pascasarjana Teknik Kimia',
            ),
            49 => 
            array (
                'id_department' => 50,
                'name_department' => 'Pascasarjana Teknik Mesin',
            ),
            50 => 
            array (
                'id_department' => 51,
                'name_department' => 'PDPM',
            ),
            51 => 
            array (
                'id_department' => 52,
                'name_department' => 'Pengembangan Smart Eco Campus',
            ),
            52 => 
            array (
                'id_department' => 53,
                'name_department' => 'Pengendalian, Pengelolaan, dan Pengawasan Program',
            ),
            53 => 
            array (
                'id_department' => 54,
                'name_department' => 'Perencanaan Wilayah dan Kota',
            ),
            54 => 
            array (
                'id_department' => 55,
                'name_department' => 'Perpustakaan',
            ),
            55 => 
            array (
                'id_department' => 56,
            'name_department' => 'PK2 (SAC)',
            ),
            56 => 
            array (
                'id_department' => 57,
                'name_department' => 'PMSC',
            ),
            57 => 
            array (
                'id_department' => 58,
                'name_department' => 'Prodi Teknologi Kedokteran',
            ),
            58 => 
            array (
                'id_department' => 59,
                'name_department' => 'PT ITS',
            ),
            59 => 
            array (
                'id_department' => 60,
                'name_department' => 'PT ITS Surabaya Hebat',
            ),
            60 => 
            array (
                'id_department' => 61,
                'name_department' => 'Pusat Inkubator',
            ),
            61 => 
            array (
                'id_department' => 62,
                'name_department' => 'Pusat Kajian Halal',
            ),
            62 => 
            array (
                'id_department' => 63,
                'name_department' => 'Pusat Pengelolaan HKI',
            ),
            63 => 
            array (
                'id_department' => 64,
                'name_department' => 'Pusat Publikasi Ilmiah',
            ),
            64 => 
            array (
                'id_department' => 65,
                'name_department' => 'Pusat Studi PLI',
            ),
            65 => 
            array (
                'id_department' => 66,
                'name_department' => 'Satgas PPKS ITS',
            ),
            66 => 
            array (
                'id_department' => 67,
                'name_department' => 'SDGs Center',
            ),
            67 => 
            array (
                'id_department' => 68,
                'name_department' => 'SDMO ITS',
            ),
            68 => 
            array (
                'id_department' => 69,
                'name_department' => 'Sekretariat Pasca Teknik Lingkungan',
            ),
            69 => 
            array (
                'id_department' => 70,
                'name_department' => 'Sekretaris ITS',
            ),
            70 => 
            array (
                'id_department' => 71,
                'name_department' => 'Sekretaris Rektor',
            ),
            71 => 
            array (
                'id_department' => 72,
                'name_department' => 'Sekretaris WR I',
            ),
            72 => 
            array (
                'id_department' => 73,
                'name_department' => 'Sekretaris WR III',
            ),
            73 => 
            array (
                'id_department' => 74,
                'name_department' => 'Sekretaris WR IV',
            ),
            74 => 
            array (
                'id_department' => 75,
                'name_department' => 'Senat Akademik',
            ),
            75 => 
            array (
                'id_department' => 76,
                'name_department' => 'Sistem Informasi',
            ),
            76 => 
            array (
                'id_department' => 77,
                'name_department' => 'Sistem Perkapalan',
            ),
            77 => 
            array (
                'id_department' => 78,
                'name_department' => 'SPKB',
            ),
            78 => 
            array (
                'id_department' => 79,
                'name_department' => 'Statistika',
            ),
            79 => 
            array (
                'id_department' => 80,
                'name_department' => 'Statistika Bisnis',
            ),
            80 => 
            array (
                'id_department' => 81,
                'name_department' => 'Subbagian TU Rektorat',
            ),
            81 => 
            array (
                'id_department' => 82,
                'name_department' => 'Teknik Biomedik',
            ),
            82 => 
            array (
                'id_department' => 83,
                'name_department' => 'Teknik Elektro',
            ),
            83 => 
            array (
                'id_department' => 84,
                'name_department' => 'Teknik Elektro Otomasi',
            ),
            84 => 
            array (
                'id_department' => 85,
                'name_department' => 'Teknik Fisika',
            ),
            85 => 
            array (
                'id_department' => 86,
                'name_department' => 'Teknik Geofisika',
            ),
            86 => 
            array (
                'id_department' => 87,
                'name_department' => 'Teknik Geomatika',
            ),
            87 => 
            array (
                'id_department' => 88,
                'name_department' => 'Teknik Industri',
            ),
            88 => 
            array (
                'id_department' => 89,
                'name_department' => 'Teknik Infrastruktur Sipil',
            ),
            89 => 
            array (
                'id_department' => 90,
                'name_department' => 'Teknik Instrumentasi',
            ),
            90 => 
            array (
                'id_department' => 91,
                'name_department' => 'Teknik Kelautan',
            ),
            91 => 
            array (
                'id_department' => 92,
                'name_department' => 'Teknik Kimia',
            ),
            92 => 
            array (
                'id_department' => 93,
                'name_department' => 'Teknik Kimia Industri',
            ),
            93 => 
            array (
                'id_department' => 94,
                'name_department' => 'Teknik Komputer',
            ),
            94 => 
            array (
                'id_department' => 95,
                'name_department' => 'Teknik Lingkungan',
            ),
            95 => 
            array (
                'id_department' => 96,
                'name_department' => 'Teknik Material',
            ),
            96 => 
            array (
                'id_department' => 97,
                'name_department' => 'Teknik Mesin',
            ),
            97 => 
            array (
                'id_department' => 98,
                'name_department' => 'Teknik Mesin Industri',
            ),
            98 => 
            array (
                'id_department' => 99,
                'name_department' => 'Teknik Perkapalan',
            ),
            99 => 
            array (
                'id_department' => 100,
                'name_department' => 'Teknik Sipil',
            ),
            100 => 
            array (
                'id_department' => 101,
                'name_department' => 'Transportasi Laut',
            ),
            101 => 
            array (
                'id_department' => 102,
                'name_department' => 'TU Departemen Aktuaria FSAD',
            ),
            102 => 
            array (
                'id_department' => 103,
                'name_department' => 'TU FADP',
            ),
            103 => 
            array (
                'id_department' => 104,
                'name_department' => 'TU Fakultas Vokasi',
            ),
            104 => 
            array (
                'id_department' => 105,
                'name_department' => 'TU FBMT',
            ),
            105 => 
            array (
                'id_department' => 106,
                'name_department' => 'TU FIA',
            ),
            106 => 
            array (
                'id_department' => 107,
                'name_department' => 'TU FMKSD',
            ),
            107 => 
            array (
                'id_department' => 108,
                'name_department' => 'TU FTEIC',
            ),
            108 => 
            array (
                'id_department' => 109,
                'name_department' => 'TU FTI',
            ),
            109 => 
            array (
                'id_department' => 110,
                'name_department' => 'TU FTIK',
            ),
            110 => 
            array (
                'id_department' => 111,
                'name_department' => 'TU FTK',
            ),
            111 => 
            array (
                'id_department' => 112,
                'name_department' => 'TU FTSPK',
            ),
            112 => 
            array (
                'id_department' => 113,
                'name_department' => 'UKPBJ',
            ),
            113 => 
            array (
                'id_department' => 114,
                'name_department' => 'Unit Komunikasi Publik',
            ),
            114 => 
            array (
                'id_department' => 115,
                'name_department' => 'Unit Pengelolaan dan Pengendalian Program',
            ),
            115 => 
            array (
                'id_department' => 116,
                'name_department' => 'Unit Pengelolaan, Pengendalian dan Pengawasan Program',
            ),
            116 => 
            array (
                'id_department' => 117,
                'name_department' => 'Unit Pusat Bahasa Global',
            ),
            117 => 
            array (
                'id_department' => 118,
                'name_department' => 'Unit ULH',
            ),
            118 => 
            array (
                'id_department' => 119,
                'name_department' => 'UPT Asrama',
            ),
            119 => 
            array (
                'id_department' => 120,
                'name_department' => 'UPT Fasor',
            ),
            120 => 
            array (
                'id_department' => 121,
                'name_department' => 'UPT Fasum ITS',
            ),
            121 => 
            array (
                'id_department' => 122,
                'name_department' => 'UPT Medical Center',
            ),
            122 => 
            array (
                'id_department' => 123,
                'name_department' => 'UPT Pusat Pelatihan dan Sertifikasi',
            ),
        ));
        
        
    }
}