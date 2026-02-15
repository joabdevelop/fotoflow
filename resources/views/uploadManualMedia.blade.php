<x-app-layout name="Upload Manual de Mídia">
    <div class="container mt-4 background-green-light p-4 rounded shadow-sm">
        <h2>Upload Manual para: <span class="text-primary">{{ $currentGaleria ?? 'Geral' }}</span></h2>

        <form id="uploadForm" action="{{ route('media.upload.process.submit') }}" method="POST"
            enctype="multipart/form-data">
            @csrf

            <input type="hidden" name="midia_gallery" id="midia_gallery" value="{{ $currentGaleria ?? 'Manual' }}">
            <input type="hidden" name="file_paths" id="filePaths">

            <div class="mb-3">
                <label for="mediaFiles" class="form-label">Selecione Fotos, Vídeos e JSONs (Arraste a pasta
                    aqui)</label>
                <input class="form-control" type="file" id="mediaFiles" name="mediaFiles[]" webkitdirectory
                    mozdirectory allowdirs multiple required>
            </div>

            <button type="submit" class="btn btn-primary" id="btnSubmit">Iniciar Importação</button>
        </form>
    </div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const input = document.getElementById('mediaFiles');
            const paths = {};

            for (let i = 0; i < input.files.length; i++) {
                const file = input.files[i];
                if (file.webkitRelativePath) {
                    // Normaliza para barras normais para o PHP explode('/') funcionar
                    paths[file.name] = file.webkitRelativePath.replace(/\\/g, '/');
                    console.log(`Arquivo: ${file.name} | Caminho Relativo: ${paths[file.name]}`);
                }
            }
            document.getElementById('filePaths').value = JSON.stringify(paths);
        });
    </script>
</x-app-layout>
