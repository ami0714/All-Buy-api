<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class HitPayController extends Controller
{
    public function createPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'courier_id' => ['required', 'integer', 'min:1'],
            'shipping_method_id' => ['required', 'integer', 'min:1'],
        ]);

        $apiKey = config('services.hitpay.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            report(new \RuntimeException('HitPay API key is not configured.'));

            return response()->json([
                'status' => false,
                'message' => 'Payment service is not configured.',
            ], 500);
        }

        try {
            $checkout = DB::transaction(function () use ($request, $validated): array {
                $shipping = DB::selectOne(
                    'SELECT c.id AS courier_id, sm.id AS shipping_method_id, sm.price
                    FROM couriers c
                    INNER JOIN shipping_methods sm ON sm.courier_id = c.id
                    WHERE c.id = ? AND sm.id = ?
                        AND c.is_active = 1 AND sm.is_active = 1',
                    [$validated['courier_id'], $validated['shipping_method_id']]
                );

                if ($shipping === null) {
                    throw ValidationException::withMessages([
                        'shipping_method_id' => 'The selected active courier and shipping method do not match.',
                    ]);
                }

                $address = DB::selectOne(
                    'SELECT id, recipient_name, phone, address_line1, address_line2, city, state, postcode
                    FROM addresses
                    WHERE user_id = ? AND is_default = 1
                    ORDER BY id DESC
                    LIMIT 1',
                    [$request->user()->id]
                );

                if ($address === null) {
                    throw ValidationException::withMessages([
                        'address' => 'Set a default delivery address before checking out.',
                    ]);
                }

                $cartItems = DB::select(
                    'SELECT
                        ci.variant_id,
                        ci.quantity,
                        p.name AS product_name,
                        p.is_active AS product_is_active,
                        pv.size,
                        pv.color,
                        pv.stock,
                        COALESCE(pv.price, p.default_price) AS unit_price
                    FROM cart_items ci
                    INNER JOIN product_variants pv ON pv.id = ci.variant_id
                    INNER JOIN products p ON p.id = pv.product_id
                    WHERE ci.user_id = ?
                    ORDER BY ci.id',
                    [$request->user()->id]
                );

                if ($cartItems === []) {
                    throw ValidationException::withMessages([
                        'cart' => 'Your cart is empty.',
                    ]);
                }

                $subtotalCents = 0;

                foreach ($cartItems as $item) {
                    if (! $item->product_is_active || (int) $item->stock < (int) $item->quantity) {
                        throw ValidationException::withMessages([
                            'cart' => 'A cart item is inactive or has insufficient stock.',
                        ]);
                    }

                    $subtotalCents += $this->amountToCents($item->unit_price) * (int) $item->quantity;
                }

                $shippingCents = $this->amountToCents($shipping->price);
                $totalCents = $subtotalCents + $shippingCents;
                $orderNumber = 'ORD-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(8)));
                $addressSnapshot = json_encode([
                    'recipient_name' => $address->recipient_name,
                    'phone' => $address->phone,
                    'address_line1' => $address->address_line1,
                    'address_line2' => $address->address_line2,
                    'city' => $address->city,
                    'state' => $address->state,
                    'postcode' => $address->postcode,
                ], JSON_THROW_ON_ERROR);

                DB::insert(
                    'INSERT INTO orders (
                        order_number, reference_no, user_id, address_id, address_snapshot,
                        subtotal, discount, tax, shipping_method, total_amount, currency, status
                    ) VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?)',
                    [
                        $orderNumber,
                        $orderNumber,
                        $request->user()->id,
                        $address->id,
                        $addressSnapshot,
                        $this->centsToAmount($subtotalCents),
                        $shipping->shipping_method_id,
                        $this->centsToAmount($totalCents),
                        'MYR',
                        'pending',
                    ]
                );

                $order = DB::selectOne(
                    'SELECT id FROM orders WHERE order_number = ?',
                    [$orderNumber]
                );

                foreach ($cartItems as $item) {
                    $unitPriceCents = $this->amountToCents($item->unit_price);
                    $itemSubtotalCents = $unitPriceCents * (int) $item->quantity;
                    $variantInfo = trim(($item->size ?? '').' / '.($item->color ?? ''), ' /');

                    DB::insert(
                        'INSERT INTO order_items (
                            order_id, variant_id, product_name_at_purchase, variant_info_at_purchase,
                            quantity, price, subtotal
                        ) VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [
                            $order->id,
                            $item->variant_id,
                            $item->product_name,
                            $variantInfo,
                            $item->quantity,
                            $this->centsToAmount($unitPriceCents),
                            $this->centsToAmount($itemSubtotalCents),
                        ]
                    );
                }

                DB::insert(
                    'INSERT INTO payments (order_id, gateway, reference_no, amount, status)
                    VALUES (?, ?, ?, ?, ?)',
                    [
                        $order->id,
                        'hitpay',
                        $orderNumber,
                        $this->centsToAmount($totalCents),
                        'pending',
                    ]
                );

                $user = DB::selectOne(
                    'SELECT email FROM users WHERE id = ?',
                    [$request->user()->id]
                );

                $paymentPayload = [
                    'amount' => $this->centsToAmount($totalCents),
                    'currency' => 'MYR',
                    'email' => $user->email,
                    'name' => $address->recipient_name,
                    'purpose' => 'Order '.$orderNumber,
                    'reference_number' => $orderNumber,
                ];

                if (is_string($address->phone)
                    && preg_match('/^\+[1-9]\d{1,14}$/', $address->phone) === 1) {
                    $paymentPayload['phone'] = $address->phone;
                }

                return [
                    'order_id' => $order->id,
                    'order_number' => $orderNumber,
                    'amount' => $this->centsToAmount($totalCents),
                    'payload' => $paymentPayload,
                ];
            }, attempts: 3);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to create the order.',
            ], 500);
        }

        $redirectUrl = config('services.hitpay.redirect_url');
        if (is_string($redirectUrl) && $redirectUrl !== '') {
            $checkout['payload']['redirect_url'] = $redirectUrl;
        }
     //handle payment hitpay 
        try {
            $response = Http::withHeaders([
                    'X-BUSINESS-API-KEY' => $apiKey,
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ])
                ->asForm()
                ->connectTimeout(10)
                ->timeout(30)
                ->post(
                    rtrim((string) config('services.hitpay.api_url'), '/').'/v1/payment-requests',
                    $checkout['payload']
                );
        } catch (ConnectionException $exception) {
            report($exception);
            $this->markPaymentRequestFailed($checkout['order_id'], ['message' => 'HitPay connection failed.']);

            return response()->json([
                'status' => false,
                'message' => 'Unable to connect to the payment service.',
            ], 502);
        }

        $hitPayResponse = $response->json();
        $paymentRequestId = is_array($hitPayResponse) ? ($hitPayResponse['id'] ?? null) : null;
        $checkoutUrl = is_array($hitPayResponse) ? ($hitPayResponse['url'] ?? null) : null;

        if (! $response->successful()
            || ! is_string($paymentRequestId)
            || $paymentRequestId === ''
            || ! is_string($checkoutUrl)
            || filter_var($checkoutUrl, FILTER_VALIDATE_URL) === false
            || parse_url($checkoutUrl, PHP_URL_SCHEME) !== 'https') {
            $this->markPaymentRequestFailed($checkout['order_id'], [
                'http_status' => $response->status(),
                'response' => $hitPayResponse,
            ]);
            report(new \RuntimeException('HitPay did not return a valid payment request and checkout URL.'));

            return response()->json([
                'status' => false,
                'message' => 'Unable to create a payment session.',
            ], 502);
        }

        try {
            //jika berjaya maka pending
            DB::transaction(function () use ($checkout, $paymentRequestId, $hitPayResponse): void {
                DB::update(
                    'UPDATE payments
                    SET billcode = ?, raw_response = ?
                    WHERE order_id = ? AND gateway = ? AND status = ?',
                    [
                        $paymentRequestId,
                        json_encode($hitPayResponse, JSON_THROW_ON_ERROR),
                        $checkout['order_id'],
                        'hitpay',
                        'pending',
                    ]
                );
            }, attempts: 3);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Payment session was created, but could not be saved.',
                'order_number' => $checkout['order_number'],
            ], 500);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'order_id' => $checkout['order_id'],
                'order_number' => $checkout['order_number'],
                'payment_request_id' => $paymentRequestId,
                'checkout_url' => $checkoutUrl,
                'amount' => $checkout['amount'],
                'currency' => 'MYR',
            ],
        ], 201);
    }
    //webhook jika berjaya atau gagal dan update order
    public function webhook(Request $request): JsonResponse
    {
        $salt = config('services.hitpay.salt');
        $signature = $request->header('Hitpay-Signature');
        $rawPayload = $request->getContent();

        if (! is_string($salt) || $salt === '') {
            report(new \RuntimeException('HitPay webhook salt is not configured.'));

            return response()->json([
                'status' => false,
                'message' => 'Webhook verification is not configured.',
            ], 500);
        }

        $expectedSignature = hash_hmac('sha256', $rawPayload, $salt);
        if (! is_string($signature) || ! hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid webhook signature.',
            ], 401);
        }

        try {
            $payload = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid webhook payload.',
            ], 400);
        }

        if (! is_array($payload)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid webhook payload.',
            ], 400);
        }

        $eventObject = $request->header('Hitpay-Event-Object');
        if (is_string($eventObject) && $eventObject !== '' && $eventObject !== 'payment_request') {
            return response()->json(['status' => true, 'message' => 'Event ignored.']);
        }

        $paymentRequestId = $payload['id'] ?? null;
        $orderNumber = $payload['reference_number'] ?? null;
        $paymentStatus = $payload['status'] ?? null;

        if (! is_string($paymentRequestId) || ! is_string($orderNumber)
            || ! in_array($paymentStatus, ['completed', 'failed'], true)
            || ! isset($payload['amount'], $payload['currency'])) {
            return response()->json([
                'status' => false,
                'message' => 'Webhook is missing payment details.',
            ], 422);
        }

        try {
            $result = DB::transaction(function () use (
                $payload,
                $paymentRequestId,
                $orderNumber,
                $paymentStatus
            ): string {
                $payment = DB::selectOne(
                    'SELECT
                        p.id AS payment_id,
                        p.status AS payment_status,
                        p.amount AS payment_amount,
                        p.billcode,
                        p.reference_no,
                        o.id AS order_id,
                        o.user_id,
                        o.currency,
                        o.status AS order_status
                    FROM payments p
                    INNER JOIN orders o ON o.id = p.order_id
                    WHERE p.gateway = ? AND p.billcode = ? AND p.reference_no = ?
                    LIMIT 1',
                    ['hitpay', $paymentRequestId, $orderNumber]
                );

                if ($payment === null) {
                    return 'not_found';
                }

                if ($this->amountToCents($payload['amount']) !== $this->amountToCents($payment->payment_amount)
                    || strtoupper((string) $payload['currency']) !== strtoupper((string) $payment->currency)) {
                    return 'amount_mismatch';
                }

                if ($payment->payment_status === 'paid') {
                    return 'already_processed';
                }

                $rawResponse = json_encode($payload, JSON_THROW_ON_ERROR);
                  //handle update dan insert
                if ($paymentStatus === 'failed') {
                    if ($payment->payment_status === 'pending') {
                        DB::update(
                            'UPDATE payments SET status = ?, raw_response = ?
                            WHERE id = ? AND status = ?',
                            ['failed', $rawResponse, $payment->payment_id, 'pending']
                        );

                        DB::insert(
                            'INSERT INTO order_status_history (order_id, status, note)
                            VALUES (?, ?, ?)',
                            [$payment->order_id, 'payment_failed', 'HitPay payment request failed.']
                        );
                    }

                    return 'failed';
                }

                if ($payment->payment_status !== 'pending') {
                    return 'already_processed';
                }

                $orderItems = DB::select(
                    'SELECT variant_id, quantity
                    FROM order_items
                    WHERE order_id = ?',
                    [$payment->order_id]
                );
               //handle pengurangan stock  dengam guna data dari order item
                foreach ($orderItems as $item) {
                    $updatedStock = DB::update(
                        'UPDATE product_variants
                        SET stock = stock - ?
                        WHERE id = ? AND stock >= ?',
                        [$item->quantity, $item->variant_id, $item->quantity]
                    );

                    if ($updatedStock !== 1) {
                        throw new \RuntimeException('Insufficient stock while completing a paid HitPay order.');
                    }

                    $cartItem = DB::selectOne(
                        'SELECT quantity FROM cart_items WHERE user_id = ? AND variant_id = ?',
                        [$payment->user_id, $item->variant_id]
                    );

                    if ($cartItem !== null && (int) $cartItem->quantity > (int) $item->quantity) {
                        DB::update(
                            'UPDATE cart_items
                            SET quantity = quantity - ?
                            WHERE user_id = ? AND variant_id = ?',
                            [$item->quantity, $payment->user_id, $item->variant_id]
                        );
                    } else {
                        //delete cart selepas berjaya order
                        DB::delete(
                            'DELETE FROM cart_items WHERE user_id = ? AND variant_id = ?',
                            [$payment->user_id, $item->variant_id]
                        );
                    }
                }
                //update payment status sebagai paid jika berjaya 
                DB::update(
                    'UPDATE payments
                    SET status = ?, paid_at = CURRENT_TIMESTAMP, raw_response = ?
                    WHERE id = ? AND status = ?',
                    ['paid', $rawResponse, $payment->payment_id, 'pending']
                );

                DB::update(
                    'UPDATE orders SET status = ? WHERE id = ? AND status = ?',
                    ['paid', $payment->order_id, 'pending']
                );
                 //masukkan kedalam order history
                DB::insert(
                    'INSERT INTO order_status_history (order_id, status, note)
                    VALUES (?, ?, ?)',
                    [$payment->order_id, 'paid', 'HitPay payment confirmed.']
                );

                return 'paid';
            }, attempts: 3);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to process the payment notification.',
            ], 500);
        }

        if ($result === 'not_found') {
            return response()->json([
                'status' => false,
                'message' => 'Payment request not found.',
            ], 404);
        }

        if ($result === 'amount_mismatch') {
            report(new \RuntimeException('HitPay webhook amount or currency does not match the order.'));

            return response()->json([
                'status' => false,
                'message' => 'Payment details do not match the order.',
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => match ($result) {
                'paid' => 'Payment confirmed.',
                'failed' => 'Payment failure recorded.',
                default => 'Webhook already processed.',
            },
        ]);
    }
   //fungsi untuk memudahkan jika request failded
    private function markPaymentRequestFailed(int $orderId, array $response): void
    {
        DB::transaction(function () use ($orderId, $response): void {
            $updated = DB::update(
                'UPDATE payments SET status = ?, raw_response = ?
                WHERE order_id = ? AND gateway = ? AND status = ?',
                [
                    'failed',
                    json_encode($response, JSON_THROW_ON_ERROR),
                    $orderId,
                    'hitpay',
                    'pending',
                ]
            );

            if ($updated === 1) {
                DB::insert(
                    'INSERT INTO order_status_history (order_id, status, note)
                    VALUES (?, ?, ?)',
                    [$orderId, 'payment_failed', 'Unable to create the HitPay payment request.']
                );
            }
        }, attempts: 3);
    }

    private function amountToCents(string|int|float $amount): int
    {
        $amount = (string) $amount;

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            throw new \InvalidArgumentException('Invalid monetary amount.');
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function centsToAmount(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
