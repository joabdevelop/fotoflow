<?php
// C:\laragon\www\photoflow\edit_metadata.php

$extId = $_GET['extId'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fileName = $_POST['original_file'];
    $base = pathinfo($fileName, PATHINFO_FILENAME);
    $jsonPath = "C:/Users/jcfab/Pictures/xtemp/" . $base . "_metadata.json";

    $newData = [
        "version" => 1,
        "source" => [
            "source_event" => $_POST['origin'],
            "title" => $_POST['photo_name'],
            "description" => $_POST['description'],
            "url" => $_POST['sourceUrl'],
            "originalPage" => $_POST['originalPage'],
            "timestamps" => [
                "created" => time() // Opcional: mantendo o padrão de timestamp
            ]
        ],
        "user" => [
            "media_gallery" => "Geral",
            "rating" => (int)$_POST['rating']
        ],
        "updatedAt" => date('c')
    ];

    file_put_contents(
        $jsonPath,
        json_encode($newData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

 // ⭐ JavaScript revisado para se comunicar com a extensão
    echo "<script>
   
    const extensionId = '<?= $extId ?>'; // ID dinâmico
    
    if (window.chrome && chrome.runtime) {
        // Se temos o ID, enviamos direto para ele
        if (extensionId) {
            chrome.runtime.sendMessage(extensionId, { action: 'close_metadata_tab' });
        } else {
            // Tenta enviar de forma genérica
            chrome.runtime.sendMessage({ action: 'close_metadata_tab' });
        }
    } else {
        window.open('', '_self', ''); 
        window.close();
    }
</script>";
    exit;
}

// ============================
// Lógica para carregar (GET)
// ============================
$metadataJson = $_GET['data'] ?? '{}';
$metadata = json_decode($metadataJson, true);

$file = $metadata['originalFilename'] ?? '';
$origin = $metadata['origin'] ?? '';
$sourceUrl = $metadata['sourceUrl'] ?? '';
$originalPage = $metadata['originalPage'] ?? '';
$suggestedName = $metadata['suggestedName'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Metadados</title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; display: flex; justify-content: center; padding: 50px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 400px; }
        label { font-weight: bold; font-size: 0.9em; color: #333; }
        input, textarea, select { width: 100%; margin-bottom: 15px; padding: 10px; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px; }
        button { width: 100%; padding: 10px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; }
        button:hover { background: #218838; }
    </style>
</head>
<body>
    <div class="card">
        <h3>Editar Metadados</h3>
        <form method="POST">
            <input type="hidden" name="original_file" value="<?= htmlspecialchars($file) ?>">
            <input type="hidden" name="origin" value="<?= htmlspecialchars($origin) ?>">
            <input type="hidden" name="sourceUrl" value="<?= htmlspecialchars($sourceUrl) ?>">
            <input type="hidden" name="originalPage" value="<?= htmlspecialchars($originalPage) ?>">

            <label>Nome do Arquivo:</label>
            <input type="text" value="<?= htmlspecialchars($file) ?>" disabled style="background: #eee;">

            <label>Página Original:</label>
            <input type="text" value="<?= htmlspecialchars($originalPage) ?>" readonly style="background: #f9f9f9;">

            <label>Nome da Foto (Título):</label>
            <input type="text" name="photo_name" value="<?= htmlspecialchars($suggestedName) ?>" placeholder="Ex: Perfil do Usuário" required>

            <label>Descrição:</label>
            <textarea name="description" rows="4" placeholder="Descreva o que há na imagem..."></textarea>

            <label>Avaliação (Rating):</label>
            <select name="rating">
                <option value="0">☆☆☆☆☆ (Sem nota)</option>
                <option value="1">★☆☆☆☆ (Muito Ruim)</option>
                <option value="2">★★☆☆☆ (Regular)</option>
                <option value="3">★★★☆☆ (Boa)</option>
                <option value="4">★★★★☆ (Ótima)</option>
                <option value="5" selected>★★★★★ (Favorita)</option>
            </select>

            <button type="submit">Gravar Alterações no Arquivo</button>
        </form>
    </div>
</body>
</html>