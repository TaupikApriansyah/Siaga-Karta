<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    public function create()
    {
        $districts = \App\Models\District::with('villages')->get();
        return view('complaint.create', compact('districts'));
    }

    public function store(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'reporter_name' => 'required|string|max:255',
            'reporter_email' => 'required|email|max:255',
            'reporter_address_village_id' => 'required|exists:villages,id',
            'reporter_status' => 'required|string',
            'category' => 'required|string',
            'description' => 'required|string',
            'affected_name' => 'nullable|string|max:255',
            'affected_relation' => 'nullable|string|max:255',
        ]);

        $village = \App\Models\Village::with('district')->findOrFail($validated['reporter_address_village_id']);
        $districtName = strtoupper(substr($village->district->name, 0, 3));
        $year = date('Y');
        
        $latest = \App\Models\Complaint::where('ticket_number', 'like', "SKB-{$districtName}-{$year}-%")->latest('id')->first();
        $sequence = $latest ? intval(substr($latest->ticket_number, -5)) + 1 : 1;
        $ticketNumber = sprintf("SKB-%s-%s-%05d", $districtName, $year, $sequence);

        $complaint = \App\Models\Complaint::create($validated + [
            'ticket_number' => $ticketNumber,
            'status' => 'Diterima',
            'priority' => 'Reguler',
        ]);

        \App\Models\ComplaintLog::create([
            'complaint_id' => $complaint->id,
            'status_from' => null,
            'status_to' => 'Diterima',
            'notes' => 'Pengaduan baru masuk',
        ]);

        // Send Email (Mocking for now)
        \Illuminate\Support\Facades\Log::info("Email sent to {$complaint->reporter_email} with ticket: {$ticketNumber}");

        return redirect()->route('complaint.tracking')->with('success', "Pengaduan berhasil disubmit. Nomor Tiket Anda: {$ticketNumber}");
    }

    public function tracking()
    {
        return view('complaint.tracking');
    }

    public function track(\Illuminate\Http\Request $request)
    {
        $request->validate(['ticket_number' => 'required|string']);
        $complaint = \App\Models\Complaint::with('logs.user')->where('ticket_number', $request->ticket_number)->first();
        
        if (!$complaint) {
            return back()->withErrors(['ticket_number' => 'Nomor tiket tidak ditemukan.']);
        }

        return view('complaint.tracking', compact('complaint'));
    }

    public function dashboard()
    {
        $user = auth()->user();
        
        $query = \App\Models\Complaint::query();

        // Scope by Role
        if ($user->role === 'kelurahan') {
            $query->where('reporter_address_village_id', $user->village_id);
        } elseif ($user->role === 'kecamatan') {
            $query->whereHas('village', function ($q) use ($user) {
                $q->where('district_id', $user->district_id);
            });
            // Kecamatan usually sees verified upwards
            $query->whereIn('status', ['Diverifikasi', 'Divalidasi', 'Diproses', 'Selesai']);
        } elseif ($user->role === 'kota') {
            // Kota sees Validated upwards, or Darurat
            $query->where(function($q) {
                $q->whereIn('status', ['Divalidasi', 'Diproses', 'Selesai'])
                  ->orWhere('priority', 'Darurat');
            });
        }

        $complaints = $query->latest()->get();

        $metrics = [
            'total' => $complaints->count(),
            'dalam_proses' => $complaints->whereNotIn('status', ['Selesai'])->count(),
            'selesai' => $complaints->where('status', 'Selesai')->count(),
            'darurat' => $complaints->where('priority', 'Darurat')->count(),
        ];

        return view('dashboard', compact('complaints', 'metrics'));
    }

    public function updateStatus(\Illuminate\Http\Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string',
            'notes' => 'nullable|string'
        ]);

        $complaint = \App\Models\Complaint::findOrFail($id);
        $oldStatus = $complaint->status;
        $complaint->status = $request->status;

        if ($request->has('category')) {
            $complaint->category = $request->category;
        }
        if ($request->has('priority')) {
            $complaint->priority = $request->priority;
        }

        $complaint->save();

        \App\Models\ComplaintLog::create([
            'complaint_id' => $complaint->id,
            'user_id' => auth()->id(),
            'status_from' => $oldStatus,
            'status_to' => $complaint->status,
            'notes' => $request->notes,
        ]);

        // Mock Gmail notification
        \Illuminate\Support\Facades\Log::info("Email status update sent to {$complaint->reporter_email} - New Status: {$complaint->status}");

        return back()->with('success', 'Status pengaduan berhasil diperbarui.');
    }
}
