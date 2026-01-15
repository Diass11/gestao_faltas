<?php
// Inclui a conexão à base de dados e inicia a sessão
require 'db.php';

// Proteção: Redireciona se não estiver logado ou não for professor
if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'professor') {
    header("Location: index.php"); exit;
}

$qr_code = null;
$mensagem = "";
$professor_id = $_SESSION['user_id'];

// --- Lógica para Gerar Nova Aula e QR Code ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $disciplina_id = $_POST['disciplina'];
    
    // GERAÇÃO DO TOKEN QR: string aleatória (8 bytes) em maiúsculas
    $token = strtoupper(bin2hex(random_bytes(8))); 
    
    // Inserir a nova aula na BD com validade de 10 minutos
    $stmt = $pdo->prepare("
        INSERT INTO aulas 
        (disciplina_id, professor_id, data_aula, hora_inicio, hora_fim, token_qr, qr_ativo, qr_expira_em) 
        VALUES (?, ?, CURDATE(), CURTIME(), ADDTIME(CURTIME(), '02:00:00'), ?, 1, ADDTIME(NOW(), '00:10:00'))
    ");
    
    if($stmt->execute([$disciplina_id, $professor_id, $token])) {
        $qr_code = $token;
        $mensagem = "Aula iniciada e QR Code gerado! O código é válido por 10 minutos.";
    } else {
        $mensagem = "Erro ao criar a aula. Verifique se a disciplina existe.";
    }
}

// Buscar disciplinas associadas a este professor para o menu de seleção
$stmt = $pdo->prepare("SELECT id, nome FROM disciplinas WHERE professor_responsavel_id = ?");
$stmt->execute([$professor_id]);
$disciplinas = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Painel do Professor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body class="container mt-5">
    
    <h1>Painel do Professor</h1>
    <p>Olá, <?php echo htmlspecialchars($_SESSION['nome']); ?> | <a href="index.php" class="text-danger">Sair</a></p>
    
    <p>
        <a href="relatorio_faltas.php" class="btn btn-info mb-4">
            📊 Ver Relatório de Faltas/Presenças
        </a>
    </p>

    <div class="card p-4 shadow-sm">
        <h4>Gerar Código QR para Presença</h4>
        
        <?php if($mensagem): ?>
            <div class='alert alert-<?= ($qr_code ? "success" : "danger") ?>'><?= $mensagem ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label for="disciplina_select">Selecione a Disciplina:</label>
                <select name="disciplina" id="disciplina_select" class="form-select" required>
                    <?php if (empty($disciplinas)): ?>
                        <option value="" disabled selected>Nenhuma disciplina encontrada</option>
                    <?php endif; ?>
                    <?php foreach($disciplinas as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-success" <?= empty($disciplinas) ? 'disabled' : '' ?>>Iniciar Aula e Gerar QR</button>
        </form>

        <?php if ($qr_code): ?>
            <hr>
            <div class="mt-4 text-center">
                <h5>QR Code Ativo: Leia para validar a presença</h5>
                <div id="qrcode" class="d-flex justify-content-center my-3"></div>
                <h2 class="fw-bold text-primary display-4"><?= $qr_code ?></h2>
                <p class="text-muted">O código expira em 10 minutos (podes ajustar este tempo no código PHP).</p>
            </div>
            
            <script>
                // Geração do desenho do QR Code
                new QRCode(document.getElementById("qrcode"), "<?= $qr_code ?>");
            </script>
        <?php endif; ?>
    </div>
</body>
</html>