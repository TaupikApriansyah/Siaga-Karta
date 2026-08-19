<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard SIAGA KARTA') }} - Role: {{ Str::ucfirst(auth()->user()->role) }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-blue-500">
                    <div class="text-sm text-gray-500 font-semibold uppercase">Total Pengaduan</div>
                    <div class="text-3xl font-bold text-gray-800 mt-2">{{ $metrics['total'] ?? 0 }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-yellow-500">
                    <div class="text-sm text-gray-500 font-semibold uppercase">Dalam Proses</div>
                    <div class="text-3xl font-bold text-gray-800 mt-2">{{ $metrics['dalam_proses'] ?? 0 }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-green-500">
                    <div class="text-sm text-gray-500 font-semibold uppercase">Selesai</div>
                    <div class="text-3xl font-bold text-gray-800 mt-2">{{ $metrics['selesai'] ?? 0 }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-red-500">
                    <div class="text-sm text-gray-500 font-semibold uppercase">Darurat</div>
                    <div class="text-3xl font-bold text-gray-800 mt-2">{{ $metrics['darurat'] ?? 0 }}</div>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 overflow-x-auto">
                    
                    @if (session('success'))
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                            {{ session('success') }}
                        </div>
                    @endif

                    <table class="w-full text-sm text-left text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-3">No. Tiket</th>
                                <th class="px-6 py-3">Waktu</th>
                                <th class="px-6 py-3">Kategori & Prioritas</th>
                                <th class="px-6 py-3">Pelapor</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($complaints as $c)
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">{{ $c->ticket_number }}</td>
                                <td class="px-6 py-4">{{ $c->created_at->format('d M Y H:i') }}</td>
                                <td class="px-6 py-4">
                                    <span class="block font-bold text-gray-800">{{ $c->category }}</span>
                                    <span class="px-2 py-0.5 rounded text-xs mt-1 inline-block
                                        @if($c->priority == 'Darurat') bg-red-100 text-red-800 
                                        @elseif($c->priority == 'Prioritas') bg-yellow-100 text-yellow-800 
                                        @else bg-blue-100 text-blue-800 @endif">
                                        {{ $c->priority }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    {{ $c->reporter_name }}<br>
                                    <span class="text-xs text-gray-400">{{ $c->village->name }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 rounded text-xs font-semibold
                                        @if($c->status == 'Selesai') bg-green-100 text-green-800 
                                        @elseif($c->status == 'Diterima') bg-gray-200 text-gray-800
                                        @else bg-blue-100 text-blue-800 @endif">
                                        {{ $c->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <button onclick="document.getElementById('modal-{{ $c->id }}').classList.remove('hidden')" class="text-blue-600 hover:underline">Kelola</button>
                                </td>
                            </tr>

                            <!-- Modal -->
                            <div id="modal-{{ $c->id }}" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
                                <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                                    <div class="mt-3">
                                        <h3 class="text-lg leading-6 font-medium text-gray-900 flex justify-between">
                                            <span>Kelola Pengaduan: {{ $c->ticket_number }}</span>
                                            <button onclick="document.getElementById('modal-{{ $c->id }}').classList.add('hidden')" class="text-red-500 font-bold">&times;</button>
                                        </h3>
                                        <div class="mt-2 px-7 py-3">
                                            <div class="grid grid-cols-2 gap-4 text-sm text-gray-600 mb-4 bg-gray-50 p-4 rounded">
                                                <div><strong>Uraian:</strong> <p class="mt-1">{{ $c->description }}</p></div>
                                                <div>
                                                    <strong>Data Pelapor:</strong><br>
                                                    {{ $c->reporter_name }}<br>
                                                    {{ $c->reporter_email }}<br>
                                                    Status: {{ $c->reporter_status }}
                                                </div>
                                            </div>

                                            <form action="{{ route('complaint.update_status', $c->id) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <div class="grid grid-cols-2 gap-4 mb-4">
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">Update Status</label>
                                                        <select name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                                            <option value="Diterima" @selected($c->status == 'Diterima')>Diterima</option>
                                                            <option value="Diverifikasi" @selected($c->status == 'Diverifikasi')>Diverifikasi (Kelurahan)</option>
                                                            <option value="Divalidasi" @selected($c->status == 'Divalidasi')>Divalidasi (Kecamatan)</option>
                                                            <option value="Diproses" @selected($c->status == 'Diproses')>Diproses (OPD)</option>
                                                            <option value="Selesai" @selected($c->status == 'Selesai')>Selesai</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">Update Prioritas</label>
                                                        <select name="priority" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                                            <option value="Reguler" @selected($c->priority == 'Reguler')>Reguler</option>
                                                            <option value="Prioritas" @selected($c->priority == 'Prioritas')>Prioritas</option>
                                                            <option value="Darurat" @selected($c->priority == 'Darurat')>Darurat</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-4">
                                                    <label class="block text-sm font-medium text-gray-700">Catatan Tindak Lanjut</label>
                                                    <textarea name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" placeholder="Ketik catatan untuk warga (opsional)..."></textarea>
                                                    <p class="text-xs text-gray-500 mt-1">Warga akan menerima notifikasi via Email (Gmail) saat Anda menyimpan perubahan ini.</p>
                                                </div>

                                                <div class="flex justify-end">
                                                    <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 transition">Simpan Perubahan & Beritahu Warga</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">Belum ada data pengaduan yang sesuai.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
