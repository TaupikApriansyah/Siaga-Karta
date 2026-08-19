<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIAGA KARTA - Lacak Pengaduan</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-3xl mx-auto p-4 py-8">
        <div class="bg-white rounded-lg shadow-xl overflow-hidden">
            <div class="bg-blue-600 text-white p-6 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Lacak Tiket Pengaduan</h1>
                    <p class="text-blue-100 mt-1">Pantau status laporan Anda secara real-time</p>
                </div>
                <a href="{{ route('complaint.create') }}" class="text-white hover:text-blue-200 text-sm font-semibold underline">← Kembali Buat Laporan</a>
            </div>

            <div class="p-6">
                @if (session('success'))
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                        <strong class="font-bold">Berhasil!</strong>
                        <span class="block sm:inline">{{ session('success') }}</span>
                    </div>
                @endif

                <form action="{{ route('complaint.track') }}" method="POST" class="mb-8">
                    @csrf
                    <div class="flex gap-4">
                        <input type="text" name="ticket_number" class="flex-1 border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200" placeholder="Masukkan Nomor Tiket (Contoh: SKB-AND-2026-00001)" required value="{{ old('ticket_number') }}">
                        <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-6 rounded hover:bg-blue-700 transition shadow-md">Lacak</button>
                    </div>
                    @error('ticket_number')
                        <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                    @enderror
                </form>

                @if(isset($complaint))
                    <div class="border rounded-lg p-6 bg-gray-50">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">Tiket: {{ $complaint->ticket_number }}</h3>
                                <p class="text-sm text-gray-600">{{ $complaint->created_at->format('d M Y H:i') }} • {{ $complaint->category }}</p>
                            </div>
                            <span class="px-3 py-1 text-sm font-semibold rounded-full 
                                @if($complaint->status == 'Selesai') bg-green-100 text-green-800 
                                @elseif($complaint->status == 'Diterima') bg-gray-200 text-gray-800
                                @else bg-blue-100 text-blue-800 @endif">
                                {{ $complaint->status }}
                            </span>
                        </div>
                        
                        <div class="mb-6">
                            <h4 class="text-sm font-bold text-gray-700 uppercase mb-2">Riwayat Proses</h4>
                            <div class="relative border-l-2 border-gray-200 ml-3 space-y-4">
                                @foreach($complaint->logs()->latest()->get() as $log)
                                <div class="relative pl-6">
                                    <div class="absolute w-3 h-3 bg-blue-600 rounded-full -left-[7px] top-1.5 border-2 border-white"></div>
                                    <p class="text-sm font-semibold text-gray-800">{{ $log->status_to }}</p>
                                    <p class="text-xs text-gray-500 mb-1">{{ $log->created_at->format('d M Y H:i') }}</p>
                                    @if($log->notes)
                                        <p class="text-sm text-gray-600 bg-white p-2 rounded border mt-1">{{ $log->notes }}</p>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
