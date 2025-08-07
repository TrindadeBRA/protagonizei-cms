<?php
$start = microtime(true);

// Duração desejada (em segundos) – você pode mudar aqui para testar
$duration = 400; // 400 segundos

echo "Iniciando script de $duration segundos...<br>";

for ($i = 0; $i < $duration; $i++) {
    sleep(1); // Espera 1 segundo
    echo "Passaram $i segundos...<br>";
    flush(); // Envia o output parcial ao navegador
    ob_flush();
}

$end = microtime(true);
$total = $end - $start;

echo "<br>Finalizado em $total segundos.";
