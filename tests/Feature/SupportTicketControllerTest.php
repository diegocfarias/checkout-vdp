<?php

namespace Tests\Feature;

use App\Mail\SupportTicketCreatedMail;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class SupportTicketControllerTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_customer_can_create_ticket_for_own_order_with_attachment(): void
    {
        $customer = $this->createCustomer(['email' => 'cliente@example.com']);
        $order = $this->createOrder(['customer_id' => $customer->id]);
        $file = UploadedFile::fake()->create('comprovante.pdf', 120, 'application/pdf');

        $this->actingAs($customer, 'customer')
            ->post(route('customer.support.store'), [
                'order_id' => $order->id,
                'subject' => 'payment_issue',
                'message' => 'Preciso de ajuda com o pagamento.',
                'attachments' => [$file],
            ])
            ->assertRedirect();

        $ticket = SupportTicket::firstOrFail();
        $this->assertSame($customer->id, $ticket->customer_id);
        $this->assertSame($order->id, $ticket->order_id);
        $this->assertSame('payment_issue', $ticket->subject);
        $this->assertSame('open', $ticket->status);

        $attachment = $ticket->attachments()->firstOrFail();
        $this->assertSame($customer->id, $attachment->uploaded_by_customer_id);
        $this->assertSame('comprovante.pdf', $attachment->original_name);
        $this->assertFalse($attachment->is_internal);
        Storage::disk('local')->assertExists($attachment->path);
        Mail::assertSent(SupportTicketCreatedMail::class);
    }

    public function test_customer_support_index_only_lists_own_tickets(): void
    {
        $customer = $this->createCustomer(['email' => 'cliente@example.com']);
        $other = $this->createCustomer(['email' => 'outro@example.com']);
        $ownTicket = SupportTicket::create([
            'customer_id' => $customer->id,
            'subject' => 'general',
            'message' => 'Ticket do cliente',
            'status' => 'open',
            'priority' => 'normal',
        ]);
        $otherTicket = SupportTicket::create([
            'customer_id' => $other->id,
            'subject' => 'general',
            'message' => 'Ticket de outro cliente',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.support.index'))
            ->assertOk()
            ->assertViewHas('tickets', fn ($tickets): bool => $tickets->getCollection()->pluck('id')->all() === [$ownTicket->id]
                && ! $tickets->getCollection()->pluck('id')->contains($otherTicket->id));
    }

    public function test_customer_cannot_create_ticket_for_another_customer_order(): void
    {
        $customer = $this->createCustomer(['email' => 'cliente@example.com']);
        $other = $this->createCustomer(['email' => 'outro@example.com']);
        $otherOrder = $this->createOrder(['customer_id' => $other->id]);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.support.store'), [
                'order_id' => $otherOrder->id,
                'subject' => 'general',
                'message' => 'Tentativa inválida.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('support_tickets', 0);
    }

    public function test_customer_can_reply_with_attachment_and_reopen_awaiting_customer_ticket(): void
    {
        $customer = $this->createCustomer(['email' => 'cliente@example.com']);
        $ticket = SupportTicket::create([
            'customer_id' => $customer->id,
            'subject' => 'general',
            'message' => 'Mensagem inicial',
            'status' => 'awaiting_customer',
            'priority' => 'normal',
        ]);
        $file = UploadedFile::fake()->image('print.png');

        $this->actingAs($customer, 'customer')
            ->post(route('customer.support.reply', $ticket), [
                'message' => '',
                'attachments' => [$file],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Resposta enviada com sucesso!');

        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'customer_id' => $customer->id,
            'message' => 'Anexo enviado.',
        ]);
        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'status' => 'in_progress',
        ]);

        $message = $ticket->messages()->firstOrFail();
        $attachment = $message->attachments()->firstOrFail();
        $this->assertSame('print.png', $attachment->original_name);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_reply_rejects_closed_or_other_customer_ticket(): void
    {
        $customer = $this->createCustomer(['email' => 'cliente@example.com']);
        $other = $this->createCustomer(['email' => 'outro@example.com']);
        $closedTicket = SupportTicket::create([
            'customer_id' => $customer->id,
            'subject' => 'general',
            'message' => 'Fechado',
            'status' => 'closed',
            'priority' => 'normal',
        ]);
        $otherTicket = SupportTicket::create([
            'customer_id' => $other->id,
            'subject' => 'general',
            'message' => 'Outro',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.support.reply', $closedTicket), [
                'message' => 'Nova resposta',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Este atendimento já foi encerrado.');

        $this->actingAs($customer, 'customer')
            ->post(route('customer.support.reply', $otherTicket), [
                'message' => 'Nova resposta',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('support_ticket_messages', 0);
    }

    public function test_customer_can_view_and_download_visible_attachment(): void
    {
        $customer = $this->createCustomer(['email' => 'cliente@example.com']);
        $ticket = SupportTicket::create([
            'customer_id' => $customer->id,
            'subject' => 'general',
            'message' => 'Mensagem',
            'status' => 'open',
            'priority' => 'normal',
        ]);
        Storage::disk('local')->put("support-ticket-attachments/{$ticket->uuid}/comprovante.pdf", '%PDF-1.4');
        $attachment = $ticket->attachments()->create([
            'uploaded_by_customer_id' => $customer->id,
            'disk' => 'local',
            'path' => "support-ticket-attachments/{$ticket->uuid}/comprovante.pdf",
            'original_name' => 'comprovante.pdf',
            'mime_type' => 'application/pdf',
            'size' => 8,
            'is_internal' => false,
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.support.attachments.view', [$ticket, $attachment]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($customer, 'customer')
            ->get(route('customer.support.attachments.download', [$ticket, $attachment]))
            ->assertOk()
            ->assertDownload('comprovante.pdf');
    }

    public function test_customer_support_detail_hides_internal_notes_and_attachments(): void
    {
        $customer = $this->createCustomer(['email' => 'cliente@example.com']);
        $agent = User::factory()->create(['role' => 'support', 'is_active' => true]);
        $ticket = SupportTicket::create([
            'customer_id' => $customer->id,
            'subject' => 'general',
            'message' => 'Mensagem inicial visivel',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $publicMessage = $ticket->messages()->create([
            'user_id' => $agent->id,
            'message' => 'Resposta publica do suporte',
            'is_internal_note' => false,
        ]);
        $internalMessage = $ticket->messages()->create([
            'user_id' => $agent->id,
            'message' => 'Nota interna com dados sensiveis',
            'is_internal_note' => true,
        ]);

        $ticket->attachments()->create($this->attachmentAttributes($ticket, [
            'original_name' => 'anexo-publico.txt',
            'support_ticket_message_id' => $publicMessage->id,
            'is_internal' => false,
        ]));
        $ticket->attachments()->create($this->attachmentAttributes($ticket, [
            'original_name' => 'anexo-interno-inicial.txt',
            'is_internal' => true,
        ]));
        $ticket->attachments()->create($this->attachmentAttributes($ticket, [
            'original_name' => 'anexo-nota-interna.txt',
            'support_ticket_message_id' => $internalMessage->id,
            'is_internal' => true,
        ]));

        $this->actingAs($customer, 'customer')
            ->get(route('customer.support.show', $ticket))
            ->assertOk()
            ->assertSee('Mensagem inicial visivel')
            ->assertSee('Resposta publica do suporte')
            ->assertSee('anexo-publico.txt')
            ->assertDontSee('Nota interna com dados sensiveis')
            ->assertDontSee('anexo-interno-inicial.txt')
            ->assertDontSee('anexo-nota-interna.txt');
    }

    public function test_customer_view_downloads_visible_non_previewable_attachment(): void
    {
        $customer = $this->createCustomer(['email' => 'cliente@example.com']);
        $ticket = SupportTicket::create([
            'customer_id' => $customer->id,
            'subject' => 'general',
            'message' => 'Mensagem',
            'status' => 'open',
            'priority' => 'normal',
        ]);
        $attachment = $ticket->attachments()->create($this->attachmentAttributes($ticket, [
            'original_name' => 'historico.txt',
            'mime_type' => 'text/plain',
        ]));

        $this->actingAs($customer, 'customer')
            ->get(route('customer.support.attachments.view', [$ticket, $attachment]))
            ->assertOk()
            ->assertDownload('historico.txt');
    }

    public function test_customer_ticket_attachments_reject_invalid_type_and_too_many_files(): void
    {
        $customer = $this->createCustomer(['email' => 'cliente@example.com']);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.support.store'), [
                'subject' => 'general',
                'message' => 'Arquivo invalido.',
                'attachments' => [
                    UploadedFile::fake()->create('script.exe', 1, 'application/x-msdownload'),
                ],
            ])
            ->assertSessionHasErrors(['attachments.0']);

        $files = [];
        for ($i = 1; $i <= 6; $i++) {
            $files[] = UploadedFile::fake()->create("arquivo-{$i}.txt", 1, 'text/plain');
        }

        $this->actingAs($customer, 'customer')
            ->post(route('customer.support.store'), [
                'subject' => 'general',
                'message' => 'Arquivos demais.',
                'attachments' => $files,
            ])
            ->assertSessionHasErrors(['attachments']);

        $this->assertDatabaseCount('support_tickets', 0);
    }

    public function test_customer_cannot_access_internal_or_foreign_attachment(): void
    {
        $customer = $this->createCustomer(['email' => 'cliente@example.com']);
        $other = $this->createCustomer(['email' => 'outro@example.com']);
        $ticket = SupportTicket::create([
            'customer_id' => $customer->id,
            'subject' => 'general',
            'message' => 'Mensagem',
            'status' => 'open',
            'priority' => 'normal',
        ]);
        $otherTicket = SupportTicket::create([
            'customer_id' => $other->id,
            'subject' => 'general',
            'message' => 'Outra mensagem',
            'status' => 'open',
            'priority' => 'normal',
        ]);
        $internalAttachment = $ticket->attachments()->create($this->attachmentAttributes($ticket, [
            'is_internal' => true,
        ]));
        $foreignAttachment = $otherTicket->attachments()->create($this->attachmentAttributes($otherTicket));

        $this->actingAs($customer, 'customer')
            ->get(route('customer.support.attachments.view', [$ticket, $internalAttachment]))
            ->assertNotFound();

        $this->actingAs($customer, 'customer')
            ->get(route('customer.support.attachments.view', [$otherTicket, $foreignAttachment]))
            ->assertNotFound();
    }

    public function test_show_only_allows_ticket_owner(): void
    {
        $customer = $this->createCustomer(['email' => 'cliente@example.com']);
        $other = $this->createCustomer(['email' => 'outro@example.com']);
        $order = $this->createOrder([
            'customer_id' => $customer->id,
            'tracking_code' => 'VDP-TCK1',
        ]);
        $ticket = SupportTicket::create([
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'subject' => 'general',
            'message' => 'Mensagem',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.support.show', $ticket))
            ->assertOk()
            ->assertViewIs('customer.support-detail')
            ->assertSee('Ir para o pedido')
            ->assertSee('VDP-TCK1');

        $this->actingAs($other, 'customer')
            ->get(route('customer.support.show', $ticket))
            ->assertNotFound();
    }

    public function test_customer_can_open_priority_cancellation_request_for_order_inside_rules(): void
    {
        Carbon::setTestNow('2026-05-24 10:00:00');

        $customer = $this->createCustomer(['email' => 'cliente@example.com']);
        $search = $this->createFlightSearch(['outbound_date' => '2026-06-10']);
        $order = $this->createOrder([
            'customer_id' => $customer->id,
            'flight_search_id' => $search->id,
            'status' => 'awaiting_emission',
            'paid_at' => now()->subHours(3),
        ]);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.order.cancellation.store', $order), [
                'reason' => 'regret_24h',
                'message' => 'Quero cancelar todos os trechos.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Solicitação de cancelamento aberta com prioridade. Nossa equipe vai tratar conforme as regras aplicáveis.');

        $ticket = SupportTicket::firstOrFail();
        $this->assertSame('cancellation', $ticket->subject);
        $this->assertSame('urgent', $ticket->priority);
        $this->assertSame('regret_24h', $ticket->cancellation_reason);
        $this->assertTrue($ticket->cancellation_within_policy);
        $this->assertTrue($ticket->cancellation_policy_snapshot['free_cancellation_window']);
        $this->assertStringContainsString('Dentro da janela de cancelamento sem custo', $ticket->message);
        $this->assertStringContainsString('Quero cancelar todos os trechos.', $ticket->message);
        Mail::assertSent(SupportTicketCreatedMail::class);
    }

    public function test_cancellation_request_outside_automatic_window_is_not_priority(): void
    {
        Carbon::setTestNow('2026-05-24 10:00:00');

        $customer = $this->createCustomer(['email' => 'cliente@example.com']);
        $search = $this->createFlightSearch(['outbound_date' => '2026-05-29']);
        $order = $this->createOrder([
            'customer_id' => $customer->id,
            'flight_search_id' => $search->id,
            'status' => 'completed',
            'paid_at' => now()->subDays(2),
        ]);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.order.cancellation.store', $order), [
                'reason' => 'medical_or_personal',
                'message' => 'Preciso verificar multas.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Solicitação de cancelamento registrada. Como está fora do prazo de cancelamento sem custo, não há reembolso para cancelamento voluntário.');

        $ticket = SupportTicket::firstOrFail();
        $this->assertSame('normal', $ticket->priority);
        $this->assertFalse($ticket->cancellation_within_policy);
        $this->assertStringContainsString('Fora do prazo de cancelamento sem custo', $ticket->message);
        $this->assertStringContainsString('nao geram reembolso', $ticket->message);
    }

    public function test_cancellation_request_requires_owner_and_reuses_existing_open_ticket(): void
    {
        $customer = $this->createCustomer(['email' => 'cliente@example.com']);
        $other = $this->createCustomer(['email' => 'outro@example.com']);
        $order = $this->createOrder(['customer_id' => $customer->id]);
        $foreignOrder = $this->createOrder(['customer_id' => $other->id]);

        $existing = SupportTicket::create([
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'subject' => 'cancellation',
            'message' => 'Cancelamento existente',
            'status' => 'open',
            'priority' => 'urgent',
            'cancellation_reason' => 'regret_24h',
            'cancellation_within_policy' => true,
        ]);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.order.cancellation.store', $foreignOrder), [
                'reason' => 'regret_24h',
            ])
            ->assertNotFound();

        $this->actingAs($customer, 'customer')
            ->post(route('customer.order.cancellation.store', $order), [
                'reason' => 'wrong_data',
            ])
            ->assertRedirect(route('customer.support.show', $existing))
            ->assertSessionHas('success', 'Já existe uma solicitação de cancelamento aberta para este pedido.');

        $this->assertDatabaseCount('support_tickets', 1);
    }

    private function createCustomer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Cliente Teste',
            'email' => fake()->unique()->safeEmail(),
            'document' => '529.982.247-25',
            'phone' => '11999999999',
            'status' => 'active',
        ], $attributes));
    }

    private function attachmentAttributes(SupportTicket $ticket, array $overrides = []): array
    {
        $path = "support-ticket-attachments/{$ticket->uuid}/arquivo.txt";
        Storage::disk('local')->put($path, 'conteudo');

        return array_merge([
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'arquivo.txt',
            'mime_type' => 'text/plain',
            'size' => 8,
            'is_internal' => false,
        ], $overrides);
    }
}
