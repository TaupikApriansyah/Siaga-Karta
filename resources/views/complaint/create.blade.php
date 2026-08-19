<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIAGA KARTA - Buat Pengaduan</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-3xl mx-auto p-4 py-8">
        <div class="bg-white rounded-lg shadow-xl overflow-hidden">
            <div class="bg-blue-600 text-white p-6">
                <h1 class="text-2xl font-bold">SIAGA KARTA</h1>
                <p class="text-blue-100 mt-2">Sistem Informasi Aduan dan Gerak Cepat Karang Taruna Kota Bandung</p>
                <p class="text-sm italic mt-1">"Satu Aduan, Satu Nomor Tiket, Terpantau Sampai Selesai."</p>
            </div>

            <div class="p-6">
                @if (session('success'))
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <strong class="font-bold">Berhasil!</strong>
                        <span class="block sm:inline">{{ session('success') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                        <ul class="list-disc pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('complaint.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    
                    <h2 class="text-xl font-semibold mb-4 text-gray-800 border-b pb-2">A. Data Pelapor</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Nama Lengkap</label>
                            <input type="text" name="reporter_name" class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200" required>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Email Aktif</label>
                            <input type="email" name="reporter_email" class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200" required placeholder="email@contoh.com">
                            <p class="text-xs text-gray-500 mt-1">Digunakan untuk mengirim notifikasi status pengaduan.</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-gray-700 font-medium mb-2">Alamat Domisili (Kelurahan/Kecamatan)</label>
                            <select name="reporter_address_village_id" class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200" required>
                                <option value="">Pilih Kelurahan...</option>
                                @foreach($districts as $district)
                                    <optgroup label="{{ $district->name }}">
                                        @foreach($district->villages as $village)
                                            <option value="{{ $village->id }}">{{ $village->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-gray-700 font-medium mb-2">Status Pelapor</label>
                            <select name="reporter_status" class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200" required>
                                <option value="Warga Terdampak Langsung">Warga Terdampak Langsung</option>
                                <option value="Kerabat/Keluarga">Kerabat/Keluarga</option>
                                <option value="Tetangga/Warga Lain">Tetangga/Warga Lain</option>
                                <option value="Pengurus RT/RW">Pengurus RT/RW</option>
                            </select>
                        </div>
                    </div>

                    <h2 class="text-xl font-semibold mb-4 text-gray-800 border-b pb-2">B. Data Pengaduan</h2>
                    <div class="grid grid-cols-1 gap-4 mb-6">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Jenis Pengaduan</label>
                            <select name="category" class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200" required>
                                <option value="">Pilih Kategori...</option>
                                <option value="Kesehatan">Kesehatan</option>
                                <option value="BPJS">BPJS</option>
                                <option value="Ambulance">Ambulance</option>
                                <option value="Orang Terlantar">Orang Terlantar</option>
                                <option value="Data Sosial / Desil">Data Sosial / Desil</option>
                                <option value="Lansia / Disabilitas">Lansia / Disabilitas</option>
                                <option value="Bantuan Sosial">Bantuan Sosial</option>
                                <option value="Anak & Keluarga">Anak & Keluarga</option>
                                <option value="Kebencanaan">Kebencanaan</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Uraian Aduan</label>
                            <textarea name="description" rows="4" class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200" required placeholder="Jelaskan secara detail kejadian atau bantuan yang dibutuhkan..."></textarea>
                        </div>
                    </div>

                    <h2 class="text-xl font-semibold mb-4 text-gray-800 border-b pb-2">C. Pihak Terdampak (Opsional)</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Nama Pihak Diadukan/Dibantu</label>
                            <input type="text" name="affected_name" class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200">
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Hubungan dengan Pelapor</label>
                            <input type="text" name="affected_relation" class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200">
                        </div>
                    </div>
                    
                    <div class="mt-8 flex justify-between items-center">
                        <a href="{{ route('complaint.tracking') }}" class="text-blue-600 hover:underline">Lacak Status Tiket Anda</a>
                        <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-6 rounded hover:bg-blue-700 transition duration-300 shadow-md">Kirim Pengaduan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
