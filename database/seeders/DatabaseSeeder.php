<?php

namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $passwordHash = password_hash('password', PASSWORD_BCRYPT);

            $buyers = [
                ['uid' => '00001001', 'username' => 'demo_buyer', 'name' => 'Demo Buyer', 'email' => 'buyer@example.com', 'phone' => '0123456789'],
            ];
            $buyerNames = [
                'Aiman Hakim', 'Nur Aisyah', 'Daniel Tan', 'Siti Hajar', 'Muhammad Firdaus',
                'Priya Nair', 'Amirul Faiz', 'Nurul Izzah', 'Jason Lim', 'Farah Nadia',
                'Kavitha Raj', 'Syafiq Azman', 'Mei Ling Wong', 'Hafiz Rahman', 'Alicia Lee',
                'Nadia Zulkifli', 'Arjun Kumar', 'Irfan Ismail', 'Chloe Ng', 'Sarah Abdullah',
                'Ravi Chandran', 'Aqil Danish', 'Nur Amira', 'Ethan Goh', 'Zara Osman',
                'Adam Roslan', 'Michelle Chong', 'Iman Sofea', 'Kiran Singh', 'Luqman Hakim',
            ];

            DB::insert(
                "INSERT INTO users (uid, username, email, password, phone, role, email_verified_at)
                VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE email = VALUES(email), password = VALUES(password), phone = VALUES(phone), role = VALUES(role)",
                ['00001002', 'demo_admin', 'admin@example.com', $passwordHash, '0198765432', 'admin']
            );

            foreach ($buyerNames as $index => $name) {
                $number = $index + 1;
                $buyers[] = [
                    'uid' => sprintf('%08d', 1100 + $number),
                    'username' => sprintf('customer%02d', $number),
                    'name' => $name,
                    'email' => sprintf('customer%02d@example.test', $number),
                    'phone' => sprintf('01%08d', 10000000 + $number),
                ];
            }

            foreach ($buyers as $buyer) {
                DB::insert(
                    "INSERT INTO users (uid, username, email, password, phone, role, email_verified_at)
                    VALUES (?, ?, ?, ?, ?, 'user', CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE email = VALUES(email), password = VALUES(password), phone = VALUES(phone)",
                    [$buyer['uid'], $buyer['username'], $buyer['email'], $passwordHash, $buyer['phone']]
                );
                $buyer['id'] = (int) DB::selectOne('SELECT id FROM users WHERE username = ?', [$buyer['username']])->id;
                $buyers[array_search($buyer['username'], array_column($buyers, 'username'), true)] = $buyer;
            }

            $categoryProducts = [
                'apparel' => ['Apparel', 'Clothing for everyday wear.', [
                    ['classic-cotton-t-shirt', 'Classic Cotton T-Shirt', 39.90],
                    ['linen-button-shirt', 'Linen Button Shirt', 89.00],
                    ['everyday-hoodie', 'Everyday Hoodie', 119.00],
                    ['relaxed-fit-jeans', 'Relaxed Fit Jeans', 129.00],
                ]],
                'electronics' => ['Electronics', 'Devices and accessories for work and home.', [
                    ['wireless-earbuds', 'Wireless Earbuds', 149.00],
                    ['usb-c-charger', 'USB-C Fast Charger', 59.90],
                    ['portable-power-bank', 'Portable Power Bank', 99.00],
                    ['bluetooth-speaker', 'Bluetooth Speaker', 189.00],
                ]],
                'home-living' => ['Home & Living', 'Useful essentials for a comfortable home.', [
                    ['ceramic-mug-set', 'Ceramic Mug Set', 45.00],
                    ['cotton-bed-sheet', 'Cotton Bed Sheet Set', 159.00],
                    ['desk-lamp', 'Adjustable Desk Lamp', 79.00],
                    ['storage-basket', 'Woven Storage Basket', 35.00],
                ]],
                'beauty' => ['Beauty & Care', 'Personal care and daily beauty essentials.', [
                    ['gentle-face-cleanser', 'Gentle Face Cleanser', 32.00],
                    ['daily-moisturizer', 'Daily Moisturizer', 48.00],
                    ['sunscreen-spf50', 'Daily Sunscreen SPF50', 42.00],
                    ['hair-care-set', 'Nourishing Hair Care Set', 68.00],
                ]],
                'sports' => ['Sports & Outdoors', 'Gear for fitness, travel and outdoor activities.', [
                    ['training-yoga-mat', 'Training Yoga Mat', 69.00],
                    ['stainless-water-bottle', 'Stainless Water Bottle', 39.00],
                    ['running-waist-pack', 'Running Waist Pack', 49.00],
                    ['resistance-band-set', 'Resistance Band Set', 29.00],
                ]],
                'books' => ['Books & Stationery', 'Reading, writing and desk supplies.', [
                    ['weekly-planner', 'Undated Weekly Planner', 24.00],
                    ['hardcover-notebook', 'Hardcover Notebook', 18.00],
                    ['gel-pen-set', 'Gel Pen Set', 15.00],
                    ['study-desk-organizer', 'Desk Organizer', 34.00],
                ]],
                'toys' => ['Toys & Games', 'Games and activities for all ages.', [
                    ['wooden-building-blocks', 'Wooden Building Blocks', 59.00],
                    ['family-card-game', 'Family Card Game', 25.00],
                    ['plush-companion', 'Plush Companion', 39.00],
                    ['mini-puzzle-set', 'Mini Puzzle Set', 22.00],
                ]],
                'accessories' => ['Accessories', 'Finishing touches for daily outfits.', [
                    ['canvas-tote-bag', 'Canvas Tote Bag', 29.00],
                    ['classic-belt', 'Classic Everyday Belt', 49.00],
                    ['minimalist-wallet', 'Minimalist Wallet', 59.00],
                    ['metal-keychain', 'Metal Keychain', 12.00],
                ]],
            ];

            $productRecords = [];
            foreach ($categoryProducts as $slug => [$name, $description, $products]) {
                DB::insert(
                    'INSERT INTO categories (name, slug, description, is_active) VALUES (?, ?, ?, TRUE) ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), is_active = VALUES(is_active)',
                    [$name, $slug, $description]
                );
                $categoryId = (int) DB::selectOne('SELECT id FROM categories WHERE slug = ?', [$slug])->id;

                foreach ($products as [$productSlug, $productName, $price]) {
                    $imageUrl = 'https://placehold.co/600x600?text='.rawurlencode($productName);
                    DB::insert(
                        'INSERT INTO products (category_id, name, slug, description, default_price, image_url, is_active) VALUES (?, ?, ?, ?, ?, ?, TRUE) ON DUPLICATE KEY UPDATE image_url = COALESCE(image_url, VALUES(image_url))',
                        [$categoryId, $productName, $productSlug, $productName.' - quality checked and ready to ship.', $price, $imageUrl]
                    );
                    $productId = (int) DB::selectOne('SELECT id FROM products WHERE slug = ?', [$productSlug])->id;
                    $productRecords[] = ['id' => $productId, 'name' => $productName, 'slug' => $productSlug, 'price' => $price];

                    $options = $slug === 'apparel'
                        ? [['M', 'Black'], ['L', 'Black']]
                        : [['Standard', 'Black'], ['Standard', 'White']];

                    foreach ($options as $optionIndex => [$size, $color]) {
                        $sku = strtoupper(substr(str_replace('-', '', $productSlug), 0, 12)).'-'.strtoupper(substr($size, 0, 1)).'-'.strtoupper(substr($color, 0, 3));
                        DB::insert(
                            'INSERT INTO product_variants (product_id, sku, size, color, price, stock) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE product_id = VALUES(product_id), price = COALESCE(price, VALUES(price)), stock = VALUES(stock)',
                            [$productId, $sku, $size, $color, $price, 8 + (($productId + $optionIndex * 7) % 43)]
                        );
                        $variant = DB::selectOne(
                            'SELECT id, sku FROM product_variants WHERE product_id = ? AND size = ? AND color = ?',
                            [$productId, $size, $color]
                        );
                        $variantId = (int) $variant->id;
                        $sku = $variant->sku;
                        $productRecords[count($productRecords) - 1]['variants'][] = [
                            'id' => $variantId,
                            'sku' => $sku,
                            'size' => $size,
                            'color' => $color,
                        ];
                    }
                }
            }

            $courierRecords = [
                ['jnt', 'J&T Express', 'https://www.tracking.my/jt/{tracking_number}'],
                ['poslaju', 'Pos Laju', 'https://www.tracking.my/pos/{tracking_number}'],
                ['ninja', 'Ninja Van', 'https://www.tracking.my/ninja/{tracking_number}'],
                ['dhl-ecommerce', 'DHL eCommerce', 'https://www.dhl.com/my-en/home/tracking.html?tracking-id={tracking_number}'],
            ];
            $shippingRecords = [];
            foreach ($courierRecords as [$code, $name, $trackingUrl]) {
                $code = trim($code);
                DB::insert(
                    'INSERT INTO couriers (code, name, tracking_url_template, is_active) VALUES (?, ?, ?, TRUE) ON DUPLICATE KEY UPDATE name = VALUES(name), tracking_url_template = VALUES(tracking_url_template), is_active = VALUES(is_active)',
                    [$code, $name, $trackingUrl]
                );
                $courierId = (int) DB::selectOne('SELECT id FROM couriers WHERE code = ?', [$code])->id;

                foreach ([['Standard Delivery', 6.00, '3-5 working days'], ['Express Delivery', 12.00, '1-2 working days']] as [$service, $price, $eta]) {
                    DB::insert(
                        'INSERT INTO shipping_methods (courier_id, service_name, price, estimated_days, is_active) SELECT ?, ?, ?, ?, TRUE WHERE NOT EXISTS (SELECT 1 FROM shipping_methods WHERE courier_id = ? AND service_name = ?)',
                        [$courierId, $service, $price, $eta, $courierId, $service]
                    );
                    $shippingRecords[] = [
                        'id' => (int) DB::selectOne('SELECT id FROM shipping_methods WHERE courier_id = ? AND service_name = ?', [$courierId, $service])->id,
                        'courier_id' => $courierId,
                        'courier_code' => $code,
                        'service' => $service,
                        'price' => $price,
                    ];
                }
            }

            $locations = [
                ['Kuala Lumpur', 'Kuala Lumpur', '50000', 'Jalan Ampang'],
                ['Petaling Jaya', 'Selangor', '46200', 'Jalan SS 2'],
                ['Shah Alam', 'Selangor', '40100', 'Persiaran Kayangan'],
                ['Johor Bahru', 'Johor', '80000', 'Jalan Wong Ah Fook'],
                ['George Town', 'Pulau Pinang', '10000', 'Lebuh Chulia'],
                ['Ipoh', 'Perak', '30000', 'Jalan Sultan Idris Shah'],
                ['Kota Kinabalu', 'Sabah', '88000', 'Jalan Gaya'],
                ['Kuching', 'Sarawak', '93000', 'Jalan Padungan'],
                ['Melaka', 'Melaka', '75000', 'Jalan Merdeka'],
                ['Kuantan', 'Pahang', '25000', 'Jalan Beserah'],
            ];
            $addressRecords = [];
            foreach ($buyers as $index => $buyer) {
                $buyerId = $buyer['id'];
                $location = $locations[$index % count($locations)];
                $labels = $index % 4 === 0 ? ['Home', 'Office'] : ['Home'];

                foreach ($labels as $labelIndex => $label) {
                    $streetNumber = 10 + (($index * 13 + $labelIndex * 7) % 890);
                    $street = $location[3];
                    $line = $labelIndex === 0 ? $streetNumber.' '.$street : 'Unit '.(2 + $index % 18).', '.$streetNumber.' '.$street;
                    DB::insert(
                        'INSERT INTO addresses (user_id, label, recipient_name, phone, address_line1, city, state, postcode, is_default) SELECT ?, ?, ?, ?, ?, ?, ?, ?, ? WHERE NOT EXISTS (SELECT 1 FROM addresses WHERE user_id = ? AND label = ?)',
                        [$buyerId, $label, $buyer['name'], $buyer['phone'], $line, $location[0], $location[1], $location[2], $labelIndex === 0, $buyerId, $label]
                    );
                    $addressRecords[$buyerId][$label] = (int) DB::selectOne(
                        'SELECT id FROM addresses WHERE user_id = ? AND label = ?',
                        [$buyerId, $label]
                    )->id;
                }
            }

            $allVariants = [];
            foreach ($productRecords as $product) {
                foreach ($product['variants'] as $variant) {
                    $allVariants[] = $variant + [
                        'product_id' => $product['id'],
                        'product_name' => $product['name'],
                        'price' => $product['price'],
                    ];
                }
            }

            foreach (array_slice($buyers, 0, 18) as $buyerIndex => $buyer) {
                for ($lineIndex = 0; $lineIndex < 2; $lineIndex++) {
                    $variant = $allVariants[($buyerIndex * 3 + $lineIndex * 11) % count($allVariants)];
                    DB::insert(
                        'INSERT INTO cart_items (user_id, variant_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)',
                        [$buyer['id'], $variant['id'], 1 + (($buyerIndex + $lineIndex) % 3)]
                    );
                }
            }

            $orderStatuses = ['pending', 'pending', 'paid', 'prepare order', 'shipped', 'completed', 'completed', 'shipped', 'pending', 'paid'];
            $historySteps = ['pending', 'paid', 'prepare order', 'shipped', 'completed'];
            $today = now();

            for ($orderIndex = 1; $orderIndex <= 60; $orderIndex++) {
                $buyer = $buyers[($orderIndex - 1) % count($buyers)];
                $buyerId = $buyer['id'];
                $shipping = $shippingRecords[($orderIndex - 1) % count($shippingRecords)];
                $addressLabels = array_keys($addressRecords[$buyerId]);
                $addressLabel = $addressLabels[($orderIndex - 1) % count($addressLabels)];
                $addressId = $addressRecords[$buyerId][$addressLabel];
                $orderStatus = $orderStatuses[($orderIndex - 1) % count($orderStatuses)];
                $orderNumber = sprintf('ORD-%s-%04d', $today->format('Ymd'), $orderIndex + 1);
                $orderDate = $today->copy()->subDays(($orderIndex - 1) % 90)->setTime(9 + ($orderIndex % 10), ($orderIndex * 7) % 60);
                $lineCount = 1 + ($orderIndex % 3);
                $orderLines = [];
                $subtotal = 0;

                for ($lineIndex = 0; $lineIndex < $lineCount; $lineIndex++) {
                    $variant = $allVariants[($orderIndex * 7 + $lineIndex * 13) % count($allVariants)];
                    $quantity = 1 + (($orderIndex + $lineIndex) % 3);
                    $lineSubtotal = round($variant['price'] * $quantity, 2);
                    $subtotal += $lineSubtotal;
                    $orderLines[] = $variant + [
                        'quantity' => $quantity,
                        'subtotal' => $lineSubtotal,
                    ];
                }

                $discount = $orderIndex % 10 === 0 ? round($subtotal * 0.10, 2) : 0;
                $tax = 0;
                $total = round($subtotal - $discount + $tax + $shipping['price'], 2);
                $addressSnapshot = json_encode([
                    'recipient_name' => $buyer['name'],
                    'phone' => $buyer['phone'],
                    'address_line1' => $addressLabel === 'Office' ? 'Office delivery address' : 'Home delivery address',
                    'city' => $locations[($orderIndex - 1) % count($locations)][0],
                    'state' => $locations[($orderIndex - 1) % count($locations)][1],
                    'postcode' => $locations[($orderIndex - 1) % count($locations)][2],
                ], JSON_THROW_ON_ERROR);

                DB::insert(
                    'INSERT INTO orders (order_number, reference_no, user_id, address_id, address_snapshot, subtotal, discount, tax, shipping_method, total_amount, currency, status, order_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), address_id = VALUES(address_id), address_snapshot = VALUES(address_snapshot), subtotal = VALUES(subtotal), discount = VALUES(discount), shipping_method = VALUES(shipping_method), total_amount = VALUES(total_amount), status = VALUES(status), order_date = VALUES(order_date)',
                    [$orderNumber, 'REF-'.str_pad((string) $orderIndex, 6, '0', STR_PAD_LEFT), $buyerId, $addressId, $addressSnapshot, $subtotal, $discount, $tax, $shipping['id'], $total, 'MYR', $orderStatus, $orderDate->toDateTimeString()]
                );
                $orderId = (int) DB::selectOne('SELECT id FROM orders WHERE order_number = ?', [$orderNumber])->id;

                foreach ($orderLines as $line) {
                    $variantInfo = $line['size'].' / '.$line['color'];
                    DB::insert(
                        'INSERT INTO order_items (order_id, variant_id, product_name_at_purchase, variant_info_at_purchase, quantity, price, subtotal) SELECT ?, ?, ?, ?, ?, ?, ? WHERE NOT EXISTS (SELECT 1 FROM order_items WHERE order_id = ? AND variant_id = ?)',
                        [$orderId, $line['id'], $line['product_name'], $variantInfo, $line['quantity'], $line['price'], $line['subtotal'], $orderId, $line['id']]
                    );
                }

                $paymentStatus = $orderStatus === 'pending' && $orderIndex % 10 === 9 ? 'failed' : ($orderStatus === 'pending' ? 'pending' : 'paid');
                $billcode = sprintf('SHOP-BILL-%04d', $orderIndex);
                $paidAt = $paymentStatus === 'paid' ? $orderDate->copy()->addHours(1)->toDateTimeString() : null;
                DB::insert(
                    'INSERT INTO payments (order_id, gateway, billcode, reference_no, amount, status, paid_at, raw_response) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE order_id = VALUES(order_id), amount = VALUES(amount), status = VALUES(status), paid_at = VALUES(paid_at), raw_response = VALUES(raw_response)',
                    [$orderId, 'toyyibpay', $billcode, 'PAY-'.str_pad((string) $orderIndex, 6, '0', STR_PAD_LEFT), $total, $paymentStatus, $paidAt, json_encode(['demo' => true, 'payment_reference' => 'PAY-'.str_pad((string) $orderIndex, 6, '0', STR_PAD_LEFT)], JSON_THROW_ON_ERROR)]
                );

                if (in_array($orderStatus, ['shipped', 'completed'], true)) {
                    $shipmentStatus = $orderStatus === 'completed' ? 'delivered' : 'in_transit';
                    $trackingNumber = strtoupper($shipping['courier_code']).'DEMO'.str_pad((string) $orderIndex, 8, '0', STR_PAD_LEFT);
                    DB::insert(
                        'INSERT INTO shipments (order_id, courier_id, tracking_number, status, shipped_at, delivered_at) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE courier_id = VALUES(courier_id), tracking_number = VALUES(tracking_number), status = VALUES(status), shipped_at = VALUES(shipped_at), delivered_at = VALUES(delivered_at)',
                        [$orderId, $shipping['courier_id'], $trackingNumber, $shipmentStatus, $orderDate->copy()->addDay()->toDateTimeString(), $orderStatus === 'completed' ? $orderDate->copy()->addDays(3)->toDateTimeString() : null]
                    );
                }

                $lastStep = array_search($orderStatus, $historySteps, true);
                if ($lastStep === false) {
                    $lastStep = 0;
                }
                for ($stepIndex = 0; $stepIndex <= $lastStep; $stepIndex++) {
                    $status = $historySteps[$stepIndex];
                    $note = 'Demo order status: '.$status.'.';
                    DB::insert(
                        'INSERT INTO order_status_history (order_id, status, note) SELECT ?, ?, ? WHERE NOT EXISTS (SELECT 1 FROM order_status_history WHERE order_id = ? AND status = ? AND note = ?)',
                        [$orderId, $status, $note, $orderId, $status, $note]
                    );
                }
            }
        });
    }
}
