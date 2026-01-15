<?php
require 'db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'aluno') {
    header("Location: index.php"); exit;
}

$mensagem = "";
$tipo_msg = "";

// Processar o envio do código (seja via câmara ou manual)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $token_input = $_POST['token_qr'];
    $aluno_id = $_SESSION['user_id'];

    // 1. Verificar validade da aula
    $stmt = $pdo->prepare("SELECT id FROM aulas WHERE token_qr = ? AND qr_ativo = 1 AND qr_expira_em > NOW()");
    $stmt->execute([$token_input]);
    $aula = $stmt->fetch();

    if ($aula) {
        try {
            // 2. Registar presença
            $insert = $pdo->prepare("INSERT INTO presencas (aula_id, aluno_id, estado) VALUES (?, ?, 'presente')");
            $insert->execute([$aula['id'], $aluno_id]);
            $mensagem = "Presença confirmada! ✅";
            $tipo_msg = "success";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $mensagem = "Presença já estava registada.";
                $tipo_msg = "warning";
            } else {
                $mensagem = "Erro no sistema.";
                $tipo_msg = "danger";
            }
        }
    } else {
        $mensagem = "QR Code inválido ou expirado ❌";
        $tipo_msg = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registar Presença</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
</head>
<body class="bg-light">

<div class="container mt-4" style="max-width: 600px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5>Aluno: <strong><?php echo htmlspecialchars($_SESSION['nome']); ?></strong></h5>
        <a href="index.php" class="btn btn-outline-danger btn-sm">Sair</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white text-center">
            <h4>Validar Presença</h4>
        </div>
        <div class="card-body text-center">
            
            <?php if($mensagem): ?>
                <div class="alert alert-<?= $tipo_msg ?>"><?= $mensagem ?></div>
            <?php endif; ?>

            <div id="reader" style="width: 100%;" class="mb-3"></div>
            <p class="text-muted small">Aponte a câmara para o código do professor</p>

            <hr>

            <form method="POST" id="formPresenca">
                <div class="mb-3">
                    <label class="form-label fw-bold">Código da Aula:</label>
                    <input type="text" id="input_token" name="token_qr" 
                           class="form-control form-control-lg text-center text-uppercase" 
                           placeholder="Escrever código manual..." required>
                </div>
                <button type="submit" class="btn btn-success w-100">Confirmar Presença</button>
            </form>
        </div>
    </div>
</div>

<script>
    // Configuração do Leitor de QR Code
    function onScanSuccess(decodedText, decodedResult) {
        // 1. Toca um som de "beep" (opcional)
        // var audio = new Audio('beep.mp3'); audio.play();

        // 2. Coloca o texto lido no input
        document.getElementById('input_token').value = decodedText;
        
        // 3. Para a câmara para poupar bateria
        html5QrcodeScanner.clear();

        // 4. Submete o formulário automaticamente
        document.getElementById('formPresenca').submit();
    }

    function onScanFailure(error) {
        // Ignorar erros de leitura contínua (acontece quando a câmara não foca nada)
        // console.warn(`Erro de leitura: ${error}`);
    }

    // Inicializar o scanner (fps: frames por segundo, qrbox: tamanho da área de foco)
    let html5QrcodeScanner = new Html5QrcodeScanner(
        "reader", 
        { fps: 10, qrbox: {width: 250, height: 250} }, 
        /* verbose= */ false
    );
    
    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
</script>

</body>
</html>