<?php

namespace Tests\Feature;

use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicComplaintTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_form_creates_prd_ticket_without_citizen_nik(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/public/reports', [
            'request_uuid' => '11111111-1111-4111-8111-111111111111',
            'name' => 'Warga Bandung',
            'email' => 'warga@example.com',
            'reporter_status' => 'diri_sendiri',
            'kecamatan' => 'Andir',
            'kelurahan' => 'Campaka',
            'complaint_type' => 'kesehatan',
            'description' => 'Membutuhkan bantuan pelayanan kesehatan untuk warga.',
            'incident_location' => 'Campaka, Andir',
            'source_channel' => 'form_online',
            'consent_data' => true,
        ]);

        $response->assertCreated()->assertJsonPath('tracking_code', 'SKB-ANDIR-'.now()->format('Y').'-00001');
        $this->assertDatabaseHas('reports', [
            'reporter_email' => 'warga@example.com',
            'complaint_type' => 'kesehatan',
            'workflow_stage' => 'kelurahan',
            'priority' => 'belum_ditentukan',
            'source_channel' => 'form_online',
        ]);
        $this->assertNull(Report::firstOrFail()->citizen_id);
    }
}
