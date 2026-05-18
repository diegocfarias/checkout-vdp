<?php

namespace Tests\Feature;

use App\Filament\Resources\SupportAgentResource;
use App\Filament\Resources\SupportAgentResource\Pages\CreateSupportAgent;
use App\Filament\Resources\SupportAgentResource\Pages\EditSupportAgent;
use App\Filament\Resources\SupportAgentResource\Pages\ListSupportAgents;
use App\Filament\Resources\SupportTicketResource;
use App\Filament\Resources\SupportTicketResource\Pages\ListSupportTickets;
use App\Filament\Resources\SupportTicketResource\Pages\ViewSupportTicket;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class FilamentSupportCoverageTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_support_agent_resource_creates_lists_edits_and_scopes_support_users(): void
    {
        Carbon::setTestNow('2026-05-16 09:00:00');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $existingSupport = User::factory()->create([
            'name' => 'Atendente Existente',
            'email' => 'existente@example.com',
            'role' => 'support',
            'is_active' => true,
        ]);
        $regularAdmin = User::factory()->create([
            'name' => 'Admin Fora da Lista',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $customer = $this->createCustomer();
        $this->createTicket($customer, ['assigned_to' => $existingSupport->id]);
        $this->createTicket($customer, ['assigned_to' => $existingSupport->id, 'status' => 'resolved']);

        $this->actingAs($admin);
        $this->assertTrue(SupportAgentResource::canAccess());
        $this->assertSame([$existingSupport->id], SupportAgentResource::getEloquentQuery()->pluck('id')->all());
        $this->assertArrayHasKey('create', SupportAgentResource::getPages());

        Livewire::actingAs($admin)
            ->test(ListSupportAgents::class)
            ->assertActionExists('create')
            ->assertSee('Atendente Existente')
            ->assertDontSee($regularAdmin->email)
            ->assertTableColumnStateSet('total_tickets', 2, $existingSupport);

        Livewire::actingAs($admin)
            ->test(CreateSupportAgent::class)
            ->fillForm([
                'name' => 'Atendente Novo',
                'email' => 'atendente-novo@example.com',
                'password' => 'senha-forte',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'atendente-novo@example.com')->firstOrFail();
        $this->assertSame('support', $created->role);
        $this->assertTrue($created->is_active);
        $this->assertTrue(Hash::check('senha-forte', $created->password));

        Livewire::actingAs($admin)
            ->test(EditSupportAgent::class, ['record' => $created->getRouteKey()])
            ->assertActionExists('delete')
            ->fillForm([
                'name' => 'Atendente Editado',
                'email' => 'atendente-editado@example.com',
                'password' => '',
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $created->refresh();
        $this->assertSame('Atendente Editado', $created->name);
        $this->assertSame('atendente-editado@example.com', $created->email);
        $this->assertFalse($created->is_active);
        $this->assertTrue(Hash::check('senha-forte', $created->password));

        $this->actingAs(User::factory()->create(['role' => 'support', 'is_active' => true]));
        $this->assertFalse(SupportAgentResource::canAccess());
    }

    public function test_support_ticket_resource_table_assign_action_badges_and_navigation(): void
    {
        Carbon::setTestNow('2026-05-16 10:00:00');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $support = User::factory()->create([
            'name' => 'Atendente Um',
            'role' => 'support',
            'is_active' => true,
        ]);
        $otherSupport = User::factory()->create([
            'name' => 'Atendente Dois',
            'role' => 'support',
            'is_active' => true,
        ]);
        $customer = $this->createCustomer([
            'name' => 'Cliente Ticket',
            'email' => 'cliente-ticket@example.com',
        ]);
        $order = $this->createOrder([
            'customer_id' => $customer->id,
            'tracking_code' => 'VDP-SUP1',
        ]);
        $ticket = $this->createTicket($customer, [
            'order_id' => $order->id,
            'subject' => 'payment_issue',
            'status' => 'open',
            'priority' => 'urgent',
            'message' => 'Pagamento não identificado.',
        ]);
        $message = $ticket->messages()->create([
            'customer_id' => $customer->id,
            'message' => 'Segue comprovante.',
        ]);
        $message->forceFill([
            'created_at' => '2026-05-16 10:10:00',
            'updated_at' => '2026-05-16 10:10:00',
        ])->save();

        $closed = $this->createTicket($customer, [
            'assigned_to' => $otherSupport->id,
            'status' => 'closed',
            'priority' => 'low',
        ]);

        $this->actingAs($admin);
        $this->assertSame('1', SupportTicketResource::getNavigationBadge());
        $this->assertSame('danger', SupportTicketResource::getNavigationBadgeColor());
        $this->assertArrayHasKey('view', SupportTicketResource::getPages());

        Livewire::actingAs($support)
            ->test(ListSupportTickets::class)
            ->assertSee('Cliente Ticket')
            ->assertSee('VDP-SUP1')
            ->assertSee('16/05/2026 10:10')
            ->assertTableColumnFormattedStateSet('subject', 'Problema com pagamento', $ticket)
            ->assertTableColumnFormattedStateSet('status', 'Aberto', $ticket)
            ->assertTableColumnFormattedStateSet('priority', 'Urgente', $ticket)
            ->assertTableActionVisible('assign_to_me', $ticket)
            ->callTableAction('assign_to_me', $ticket)
            ->assertHasNoTableActionErrors()
            ->assertCanNotSeeTableRecords([$closed]);

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'assigned_to' => $support->id,
            'status' => 'in_progress',
        ]);

        $ticket->refresh();

        Livewire::actingAs($support)
            ->test(ListSupportTickets::class)
            ->assertTableColumnFormattedStateSet('status', 'Em atendimento', $ticket)
            ->assertTableActionHidden('assign_to_me', $ticket);

        $ticket->update(['status' => 'closed']);
        $this->actingAs($admin);
        $this->assertNull(SupportTicketResource::getNavigationBadge());
    }

    public function test_support_ticket_view_renders_infolist_thread_and_attachment_states(): void
    {
        Carbon::setTestNow('2026-05-16 11:00:00');
        $admin = User::factory()->create([
            'name' => 'Admin Suporte',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $support = User::factory()->create([
            'name' => 'Atendente Thread',
            'role' => 'support',
            'is_active' => true,
        ]);
        $customer = $this->createCustomer([
            'name' => 'Cliente Thread',
            'email' => 'thread@example.com',
        ]);
        $order = $this->createOrder([
            'customer_id' => $customer->id,
            'tracking_code' => 'VDP-THR1',
        ]);
        $ticket = $this->createTicket($customer, [
            'order_id' => $order->id,
            'assigned_to' => $support->id,
            'subject' => 'refund',
            'status' => 'awaiting_internal',
            'priority' => 'high',
            'message' => '<p>Preciso verificar reembolso.</p>',
            'first_response_at' => '2026-05-16 11:05:00',
            'resolved_at' => '2026-05-16 11:20:00',
        ]);

        $initialPath = "support-ticket-attachments/{$ticket->uuid}/inicial.pdf";
        Storage::disk('local')->put($initialPath, '%PDF-1.4');
        $ticket->attachments()->create([
            'uploaded_by_customer_id' => $customer->id,
            'disk' => 'local',
            'path' => $initialPath,
            'original_name' => 'inicial.pdf',
            'mime_type' => 'application/pdf',
            'size' => 2048,
            'is_internal' => false,
        ]);

        $agentMessage = $ticket->messages()->create([
            'user_id' => $support->id,
            'message' => 'Resposta do atendente.',
            'is_internal_note' => false,
        ]);
        $agentPath = "support-ticket-attachments/{$ticket->uuid}/resposta.png";
        Storage::disk('local')->put($agentPath, 'png-data');
        $ticket->attachments()->create([
            'support_ticket_message_id' => $agentMessage->id,
            'uploaded_by_user_id' => $support->id,
            'disk' => 'local',
            'path' => $agentPath,
            'original_name' => 'resposta.png',
            'mime_type' => 'image/png',
            'size' => 3 * 1024 * 1024,
            'is_internal' => false,
        ]);

        $ticket->messages()->create([
            'user_id' => $admin->id,
            'message' => 'Nota interna da equipe.',
            'is_internal_note' => true,
        ]);
        $ticket->messages()->create([
            'customer_id' => $customer->id,
            'message' => 'Obrigado pelo retorno.',
            'is_internal_note' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(ViewSupportTicket::class, ['record' => $ticket->getRouteKey()])
            ->assertSee('Informações do Ticket')
            ->assertSee('Reembolso')
            ->assertSee('Aguardando interno')
            ->assertSee('Alta')
            ->assertSee('Cliente Thread')
            ->assertSee('thread@example.com')
            ->assertSee('VDP-THR1')
            ->assertSee('Atendente Thread')
            ->assertSee('16/05/2026 11:05')
            ->assertSee('16/05/2026 11:20')
            ->assertSee('Mensagem inicial')
            ->assertSee('Preciso verificar reembolso')
            ->assertSee('inicial.pdf')
            ->assertSee('2,0 KB')
            ->assertSee('Resposta do atendente.')
            ->assertSee('resposta.png')
            ->assertSee('3,0 MB')
            ->assertSee('Nota interna da equipe.')
            ->assertSee('Obrigado pelo retorno.');

        $emptyTicket = $this->createTicket($customer, [
            'subject' => 'other',
            'status' => 'open',
            'priority' => 'normal',
            'message' => 'Sem respostas ainda.',
        ]);

        Livewire::actingAs($admin)
            ->test(ViewSupportTicket::class, ['record' => $emptyTicket->getRouteKey()])
            ->assertSee('Outro')
            ->assertSee('Nenhuma resposta ainda.');
    }

    private function createCustomer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Cliente Teste',
            'email' => fake()->unique()->safeEmail(),
            'document' => '52998224725',
            'status' => 'active',
        ], $attributes));
    }

    private function createTicket(Customer $customer, array $attributes = []): SupportTicket
    {
        return SupportTicket::create(array_merge([
            'customer_id' => $customer->id,
            'subject' => 'general',
            'status' => 'open',
            'priority' => 'normal',
            'message' => 'Mensagem inicial',
        ], $attributes));
    }
}
