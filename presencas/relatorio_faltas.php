<?php
require 'db.php';
// Proteção de acesso
if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'professor') {
    header("Location: index.php"); exit;
}

$professor_id = $_SESSION['user_id'];
$resultados = [];
$disciplina_selecionada = null;

// 1. Buscar todas as disciplinas do professor
$stmt_disciplinas = $pdo->prepare("SELECT id, nome FROM disciplinas WHERE professor_responsavel_id = ?");
$stmt_disciplinas->execute([$professor_id]);
$disciplinas = $stmt_disciplinas->fetchAll();

// 2. Processar a seleção da disciplina
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['disciplina_id']) && !empty($_GET['disciplina_id'])) {
    $disciplina_selecionada = $_GET['disciplina_id'];

    // Query para calcular FALTAS (Complexa: Total Aulas - Total Presenças)
    $sql = "
    SELECT 
        u.nome AS Nome_Aluno,
        u.numero_mecanografico AS Numero_Mec,
        COUNT(DISTINCT a.id) AS Total_Aulas_Dadas,
        COUNT(DISTINCT p.id) AS Total_Presencas,
        (COUNT(DISTINCT a.id) - COUNT(DISTINCT p.id)) AS Total_Faltas,
        ROUND((COUNT(DISTINCT p.id) / COUNT(DISTINCT a.id)) * 100, 1) AS Percentagem_Presenca
    FROM utilizadores u
    JOIN matriculas m ON u.id = m.aluno_id
    LEFT JOIN aulas a ON m.disciplina_id = a.disciplina_id 
    LEFT JOIN presencas p ON a.id = p.aula_id AND p.aluno_id = u.id
    WHERE m.disciplina_id = :disciplina_id
    AND u.tipo = 'aluno'
    GROUP BY u.id, u.nome
    HAVING Total_Aulas_Dadas > 0  -- Ignora se não houve aulas ainda
    ORDER BY Total_Faltas DESC;
    ";
    
    $stmt_resultados = $pdo->prepare($sql);
    $stmt_resultados->execute([':disciplina_id' => $disciplina_selecionada]);
    $resultados = $stmt_resultados->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Faltas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Relatório de Faltas</h2>
        <div>
            <a href="professor.php" class="btn btn-secondary">Voltar ao Painel</a>
            <a href="index.php" class="btn btn-outline-danger">Sair</a>
        </div>
    </div>

    <div class="card p-3 mb-4 shadow-sm">
        <form method="GET" class="row align-items-center">
            <div class="col-md-8">
                <label for="disciplina_id" class="form-label">Selecione a Disciplina:</label>
                <select name="disciplina_id" id="disciplina_id" class="form-select" required>
                    <option value="">-- Escolha --</option>
                    <?php foreach($disciplinas as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $disciplina_selecionada == $d['id'] ? 'selected' : '' ?>>
                            <?= $d['nome'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 mt-4 mt-md-0">
                <button type="submit" class="btn btn-primary w-100">Consultar</button>
            </div>
        </form>
    </div>

    <?php if ($disciplina_selecionada && $resultados): ?>
        <h4 class="mt-5">Resultados para a disciplina: <?= $disciplinas[array_search($disciplina_selecionada, array_column($disciplinas, 'id'))]['nome'] ?></h4>
        <table class="table table-striped table-hover mt-3">
            <thead class="table-dark">
                <tr>
                    <th>Aluno</th>
                    <th>Nº Mec.</th>
                    <th>Aulas Dadas</th>
                    <th>Presenças</th>
                    <th>Faltas ❌</th>
                    <th>Assiduidade</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resultados as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['Nome_Aluno']) ?></td>
                    <td><?= htmlspecialchars($r['Numero_Mec']) ?></td>
                    <td><?= $r['Total_Aulas_Dadas'] ?></td>
                    <td><?= $r['Total_Presencas'] ?></td>
                    <td class="<?= $r['Total_Faltas'] > 0 ? 'text-danger fw-bold' : '' ?>"><?= $r['Total_Faltas'] ?></td>
                    <td><?= $r['Percentagem_Presenca'] ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($disciplina_selecionada && empty($resultados)): ?>
        <div class="alert alert-warning mt-4">Nenhum aluno matriculado ou nenhuma aula dada nesta disciplina ainda.</div>
    <?php endif; ?>

</body>
</html>