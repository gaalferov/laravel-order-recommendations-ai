<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Mailtrap\MailtrapClient;
use Mailtrap\Mime\MailtrapEmail;
use Symfony\Component\Mime\Address;

class OrderConfirmationMailer
{
    public function send(string $recipientEmail, array $orderData): void
    {
        $subject = 'Your order is confirmed — '.$orderData['order_id'];
        $html = View::make('emails.order_confirmation', ['order' => $orderData])->render();
        $text = View::make('emails.order_confirmation_text', ['order' => $orderData])->render();

        try {
            $email = (new MailtrapEmail())
                ->from(new Address(
                    config('mail.from.address'),
                    config('mail.from.name'),
                ))
                ->to(new Address($recipientEmail))
                ->subject($subject)
                ->html($html)
                ->text($text)
                ->category(config('services.mailtrap.category', 'Order Confirmation'));

            MailtrapClient::initSendingEmails(
                apiKey: config('services.mailtrap.apiKey'),
            )->send($email);

            Log::info('Order confirmation email sent', [
                'order_id' => $orderData['order_id'],
                'recipient' => $recipientEmail,
                'recommendations_count' => count($orderData['recommendations'] ?? []),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send order confirmation email', [
                'order_id' => $orderData['order_id'],
                'recipient' => $recipientEmail,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
