<?php
include_once '../../config/database.php';

$sql = "SELECT id_pagamento, metodo_pagamento FROM pagamento ORDER BY metodo_pagamento";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($pagamentos);
?>
