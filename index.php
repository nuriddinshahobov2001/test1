<?php
include 'config.php';

function generateUniqueBarcode($pdo) {
    do {
        $barcode = random_int(10000000, 99999999);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE barcode = :barcode");
        $stmt->execute(['barcode' => $barcode]);
        $count = $stmt->fetchColumn();
    } while ($count > 0);
    
    return $barcode;
}

function bookOrder($event_id, $event_date, $ticket_adult_price, $ticket_adult_quantity, $ticket_kid_price, $ticket_kid_quantity, $barcode) {
    $data = [
        'event_id' => $event_id,
        'event_date' => $event_date,
        'ticket_adult_price' => $ticket_adult_price,
        'ticket_adult_quantity' => $ticket_adult_quantity,
        'ticket_kid_price' => $ticket_kid_price,
        'ticket_kid_quantity' => $ticket_kid_quantity,
        'barcode' => $barcode
    ];

    $ch = curl_init('https://api.site.com/book');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        echo "Ошибка cURL: $error";
        return null;
    }

    $decodedResponse = json_decode($response, true);

    if (is_null($decodedResponse)) {
        echo "Ошибка декодирования JSON. Ответ от API: $response";
        return null;
    }

    return $decodedResponse;
}


function approveOrder($barcode) {
    $data = ['barcode' => $barcode];
    
    $ch = curl_init('https://api.site.com/approve');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}
function addOrder($pdo, $event_id, $event_date, $ticket_adult_price, $ticket_adult_quantity, $ticket_kid_price, $ticket_kid_quantity, $user_id) {
    $pdo->beginTransaction();

    try {
        $equal_price = ($ticket_adult_price * $ticket_adult_quantity) + ($ticket_kid_price * $ticket_kid_quantity);

        do {
            $barcode = generateUniqueBarcode($pdo);
            $bookingResponse = bookOrder($event_id, $event_date, $ticket_adult_price, $ticket_adult_quantity, $ticket_kid_price, $ticket_kid_quantity, $barcode);

            if (is_null($bookingResponse)) {
                echo "Ошибка бронирования заказа. Повторите попытку позже.";
                $pdo->rollBack();
                return;
            }
        } while (isset($bookingResponse['error']) && $bookingResponse['error'] === 'barcode already exists');

        if (isset($bookingResponse['message']) && $bookingResponse['message'] === 'order successfully booked') {
            echo "Заказ успешно добавлен и подтвержден!";
        } else {
            echo "Ошибка бронирования заказа: " . ($bookingResponse['error'] ?? "Неизвестная ошибка");
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Ошибка добавления заказа: " . $e->getMessage());
    }
}

addOrder($pdo, 3, '2021-08-21 13:00:00', 700, 2, 450, 1, 451);
?>
