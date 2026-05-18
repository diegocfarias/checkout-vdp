<?php

namespace Tests\Feature;

use App\Mail\SupportTicketCreatedMail;
use App\Models\Customer;
use App\Models\SupportTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        $ticket = SupportTicket::create([
            'customer_id' => $customer->id,
            'subject' => 'general',
            'message' => 'Mensagem',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.support.show', $ticket))
            ->assertOk()
            ->assertViewIs('customer.support-detail');

        $this->actingAs($other, 'customer')
            ->get(route('customer.support.show', $ticket))
            ->assertNotFound();
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
