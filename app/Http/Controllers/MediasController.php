<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Services\ServiceControl;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ProcessMediaJob;
use App\Models\CopiaExata;

class MediasController extends Controller
{
    public function index(Request $request)
    {
        // 1. Busca galerias (Simplificado)
        $galerias = Media::whereNotNull('media_gallery')
            ->distinct()
            ->orderBy('media_gallery', 'asc')
            ->pluck('media_gallery');

        // 2. Lógica de Sessão / Galeria Atual (Extraído lógica para manter clareza)
        $currentGaleria = $this->resolveCurrentGaleria($request, $galerias);

        // 3. Construção da Query
        $query = Media::query();

        if ($currentGaleria) {
            $query->where('media_gallery', $currentGaleria);
        }

        // 4. Filtro de Busca
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('file_path', 'like', "%{$search}%");
            });
        }

        // 5. Filtro de Tipo
        if ($request->type === 'photos') {
            $query->whereIn('file_extension', ['.jpg', '.jpeg', '.png', '.webp', '.gif']);
        } elseif ($request->type === 'videos') {
            $query->whereIn('file_extension', ['.mp4', '.mov', '.avi', '.wmv', '.flv', '.mkv', '.webm']);
        }

        // 6. Ordenação CORRIGIDA
        if ($request->sort === 'title') {
            $query->orderBy('title', 'asc');
        } else {
            // Ordenação por data e hora real (Timestamp), não apenas DATE()
            // Isso garante que o último segundo cadastrado fique no topo
            $query->orderBy('created_at', 'desc');
        }

        // 7. Paginação
        $arquivos = $query->paginate(40)->withQueryString();

        return view('index', compact('arquivos', 'galerias', 'currentGaleria'));
    }

    /**
     * Lógica auxiliar para resolver qual galeria exibir
     */
    private function resolveCurrentGaleria(Request $request, $galerias)
    {
        if ($request->has('galeria')) {
            $selected = $request->galeria;
            session(['selected_galeria' => $selected]);
            return $selected;
        }

        $sessionGaleria = session('selected_galeria');
        if ($sessionGaleria) return $sessionGaleria;

        // Se não houver nada, define o padrão (Primeira que não seja 'Priv')
        if ($galerias->isNotEmpty()) {
            $default = $galerias->first(fn($g) => stripos($g, 'Priv') === false) ?: $galerias->first();
            session(['selected_galeria' => $default]);
            return $default;
        }

        return null;
    }

    public function uploadMediaShow()
    {
        return view('uploadManualMedia');
    }



    public function updateMedia(Request $request)
    {
        //  Log::info("Iniciando atualização de mídia com dados: " . json_encode($request->all()));
        $id = $request->input('id');
        $media = Media::find($id);

        if (!$media) {
            return response()->json(['success' => false, 'message' => 'Registro não encontrado'], 404);
        }

        $validatedData = $request->validate([
            'title'    => 'nullable|string|max:100',
            'media_gallery' => 'nullable|string|max:100',
            'description'   => 'nullable|string|max:150',
            'source_event'  => 'nullable|string|max:100',
            'is_private'       => 'nullable',
        ]);

        // Função interna para limpar apenas caracteres proibidos pelo Windows
        $sanitize = function ($text) {
            if (!$text) return '';
            // Remove caracteres que o Windows não aceita em nomes de arquivos
            return preg_replace('/[\/\\\\\:\*\?\"\<\>\|]/', '', $text);
        };

        $oldPath = $media->file_path;
        $extension = pathinfo($oldPath, PATHINFO_EXTENSION);
        $directory = pathinfo($oldPath, PATHINFO_DIRNAME);

        // 2. Montagem do nome preservando espaços
        // Formato: gallery-origin-name-description-id.ext
        $parts = [
            $sanitize($validatedData['media_gallery'] ?? 'Sem Galeria'),
            $sanitize($validatedData['source_event'] ?? 'Sem Evento'),
            $sanitize($validatedData['title'] ?? 'Sem Nome'),
            $sanitize(Str::limit($validatedData['description'] ?? 'Sem Descricao', 50, '')),
            $id
        ];

        $newName = implode(' - ', $parts) . '.' . $extension;
        $newPath = $directory . DIRECTORY_SEPARATOR . $newName;

        try {

            if ($oldPath !== $newPath && File::exists($oldPath)) {
                File::move($oldPath, $newPath);
            }

            $media->update([
                'title'    => $validatedData['title'],
                'media_gallery' => $validatedData['media_gallery'],
                'description'   => $validatedData['description'],
                'source_event'  => $validatedData['source_event'],
                'is_private'       => $validatedData['is_private'],
                'file_path'     => $newPath,
            ]);

            return response()->json(['success' => true, 'message' => 'Renomeado: ' . $newName]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }


 public function openMedia(Request $request)
{
    $queryPath = $request->get('path');

    if (file_exists($queryPath)) {
        $extension = strtolower(pathinfo($queryPath, PATHINFO_EXTENSION));
        $videoExtensions = ['mp4', 'mov', 'avi', 'wmv', 'mkv', 'webp'];

        if (in_array($extension, $videoExtensions)) {
            // Tenta VLC, se falhar usa o padrão do Windows (Reprodutor Multimídia)
            $command = 'where vlc >nul 2>nul && start /b vlc "' . $queryPath . '" || start "" "' . $queryPath . '"';
        } else {
            // Tenta IrfanView, se falhar usa o padrão do Windows (Fotos)
            $irfanPath = 'C:\Program Files\IrfanView\i_view64.exe';
            if (file_exists($irfanPath)) {
                $command = 'start /b "" "' . $irfanPath . '" "' . $queryPath . '"';
            } else {
                $command = 'start "" "' . $queryPath . '"';
            }
        }

        shell_exec($command);
        return response()->json(['success' => true]);
    }

    return response()->json(['success' => false, 'message' => 'Arquivo não encontrado']);
}

    public function duplicatesExact()
    {
        // Busca originais que possuem registros na tabela de cópias
        $originals = Media::has('copiasFisicas')
            ->with('copiasFisicas') // Eager load das cópias
            ->get();

        return view('duplicatesexact', compact('originals'));
    }


    public function destroy($id)
    {
        $media = Media::find($id);

        if (!$media) {
            return response()->json([
                'success' => false,
                'message' => 'Arquivo não encontrado.'
            ], 404);
        }

        try {
            // 1. Remove do disco físico usando o nome correto da coluna
            if (file_exists($media->file_path)) {
                unlink($media->file_path);
            }

            // 2. Limpa referências de similaridade no SQLite antes de deletar
            // Isso evita que outras fotos fiquem órfãs
            Media::where('similar_to_id', $id)->update(['similar_to_id' => null]);

            // 3. Remove do Banco de Dados SQLite
            $media->delete();

            return response()->json([
                'success' => true,
                'message' => 'Removido com sucesso!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteExact($id)
    {
        Log::info("Solicitação para deletar cópia exata ID: " . $id);

        try {
            $copia = \App\Models\CopiaExata::find($id);

            if (!$copia) {
                return response()->json(['success' => false, 'message' => 'Registro da cópia não encontrado'], 404);
            }

            $path = $copia->file_path;

            // 1. Deleta o arquivo físico do Windows
            if (File::exists($path)) {
                File::delete($path);
                Log::info("Arquivo deletado fisicamente: " . $path);
            } else {
                Log::warning("Arquivo não encontrado fisicamente, mas o registro será removido: " . $path);
            }

            // 2. Remove o registro da tabela copias_hash_exact
            $copia->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error("Erro ao deletar cópia exata ID {$id}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteAllExact()
    {
        $path = config('media.duplicates_path') . '/exact';

        collect(File::files($path))->each(fn($file) => File::delete($file));

        return response()->json([
            'success' => true,
            'message' => 'Todos os duplicados exatos foram removidos'
        ]);
    }

    public function duplicatesManterNoBanco(Request $request)
    {
        $id = $request->input('id');
        $destinationDir = config('media.library_path');

        // Busca pelo ID (Primary Key)
        $media = \App\Models\Media::find($id);

        if (!$media) {
            return response()->json(['success' => false, 'message' => 'Registro não encontrado no banco'], 404);
        }

        $oldPath = $media->file_path;

        if (!File::exists($oldPath)) {
            return response()->json(['success' => false, 'message' => 'Arquivo físico não encontrado'], 404);
        }

        try {
            $fileName = basename($oldPath);
            $finalDestination = $destinationDir . DIRECTORY_SEPARATOR . $fileName;

            // Se o arquivo já existir no destino, resolvemos o conflito
            if (File::exists($finalDestination)) {
                $finalDestination = $destinationDir . DIRECTORY_SEPARATOR . time() . '_' . $fileName;
            }

            // Move o arquivo fisicamente
            File::move($oldPath, $finalDestination);

            // Atualiza o registro garantindo que não é mais similar a ninguém
            $media->update([
                'file_path'        => $finalDestination,
                'similar_to_id'    => null,
                'similarity_score' => null
            ]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Adicione o parâmetro Request $request aqui, senão dará erro de variável indefinida
    public function duplicatesSimilares(Request $request)
    {
        // 1. Buscamos todos os registros que SÃO similares (não nulos)
        // Agrupamos pelo similar_to_id para identificar os grupos existentes
        $query = Media::whereNotNull('similar_to_id');

        // FILTRO DE BUSCA (Aplica-se aos similares encontrados)
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%");
            });
        }

        // 2. Pegamos os IDs dos pais que possuem similares ativos
        $parentIds = $query->pluck('similar_to_id')->unique();

        // 3. Agora buscamos os registros dos PAIS e carregamos seus SIMILARES
        // Isso garante que o ID 2 apareça como cabeçalho do grupo
        $roots = Media::whereIn('id', $parentIds)
            ->with(['similares' => function ($q) {
                $q->orderBy('similarity_score', 'asc');
            }])
            ->paginate(30)
            ->appends($request->all());

        $grupos = collect();
        foreach ($roots as $root) {
            // Criamos o grupo: O registro original (ID 2) + todos os que apontam para ele
            $grupos->put(
                $root->id,
                collect([$root])->merge($root->similares)
            );
        }

        return view('phash', [
            'grupos'    => $grupos,
            'paginator' => $roots
        ]);
    }

    public function deleteSimilar($id)
    {
        // Busca o registro pelo ID para pegar o path real salvo no SQLite
        $media = Media::find($id);

        if (!$media) {
            return response()->json(['success' => false, 'message' => 'Registro não encontrado no banco'], 404);
        }

        $filePath = $media->file_path;

        try {
            // Deleta o arquivo físico se ele existir
            if (File::exists($filePath)) {
                File::delete($filePath);
                Log::info("Arquivo deletado fisicamente: " . $filePath);
            }

            // Deleta o registro no banco de dados
            $media->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error("Erro ao deletar arquivo: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao deletar: ' . $e->getMessage()], 500);
        }
    }

    public function duplicatesCenario(Request $request)
    {
        // Usamos um limite maior (18) para agrupar fotos do mesmo ambiente
        $limiteCenario = 10;

        // 1. Buscamos as fotos "Originais"
        $query = Media::whereNull('similar_to_id');

        // Filtro de busca (opcional)
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $roots = $query->paginate(10)->appends($request->all());
        $grupos = collect();

        foreach ($roots as $root) {
            // Para cada foto original, buscamos no banco via SQL RAW 
            // fotos que estejam no mesmo cenário (distância <= 18)
            $similaresCenario = DB::select("
            SELECT *, bit_count(phash # :hash::bit(64)) as dist 
            FROM media_files 
            WHERE id != :id 
            AND bit_count(phash # :hash::bit(64)) <= :limite
            ORDER BY dist ASC
        ", [
                'hash' => $root->phash,
                'id' => $root->id,
                'limite' => $limiteCenario
            ]);

            // Se encontrou algo no cenário, adicionamos ao grupo
            if (!empty($similaresCenario)) {
                $grupos->put($root->id, collect([$root])->merge($similaresCenario));
            }
        }

        return view('phash', [ // Reutiliza a view de phash
            'grupos'    => $grupos,
            'paginator' => $roots,
            'titulo'    => 'Mesmo Cenário (Ambiente)'
        ]);
    }


    // Alterna o status de favorito
    public function toggleFavorite($id)
    {
        $media = Media::findOrFail($id);
        $media->is_favorite = !$media->is_favorite;
        $media->save();

        return response()->json([
            'success' => true,
            'is_favorite' => $media->is_favorite
        ]);
    }

    // Lista apenas os favoritos agrupados por photo_name
    public function favoritesIndex(Request $request)
    {
        $query = Media::where('is_favorite', true);

        // Reaproveita seus filtros de busca se desejar
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where('title', 'like', "%{$search}%");
        }

        // Agrupamento por title
        $arquivos = $query->orderBy('title')->get()->groupBy('title');

        return view('favorites', compact('arquivos'));
    }


    // #################################################################################
    // Função para apagar toda a tabela de mídias e reiniciar o banco
    // #################################################################################    

    public function apagarTabela()
    {
        try {
            DB::transaction(function () {
                // 1. Desabilita verificação de chaves estrangeiras (MySQL)
                DB::statement('SET FOREIGN_KEY_CHECKS = 0');

                // 2. Limpa as tabelas (O TRUNCATE no MySQL já reseta o auto-incremento)
                DB::table('copias_hash_exact')->truncate();
                DB::table('media_files')->truncate();

                // 3. Reabilita verificações
                DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            });

            return redirect()->route('media.index')->with('status', 'Banco de dados resetado!');
        } catch (\Exception $e) {
            Log::error("Erro ao resetar o banco: " . $e->getMessage());
            return redirect()->route('media.index')->with('error', 'Erro ao processar a limpeza.');
        }
    }

    // #################################################################################
    // Função para fazer streaming de mídia (vídeos e fotos)
    // #################################################################################

    public function stream(Request $request)
    {
        $path = $request->query('path');

        // 1. Decodifica o caminho (caso venha com %2F do navegador)
        $path = rawurldecode($path);

        // 2. Monta o caminho absoluto no seu Optiplex
        // storage_path('app/public/...') aponta para storage/app/public/
        $fullPath = storage_path('app/public/' . $path);

        // DEBUG: Vamos ver no log do Laravel exatamente o que o PHP está tentando ler
        if (!file_exists($fullPath)) {
            Log::error("Arquivo não encontrado no Windows: " . $fullPath);
            return response()->json(['error' => 'Arquivo não encontrado no disco', 'path_tentado' => $fullPath], 404);
        }

        // 3. Se for vídeo, usamos uma lógica de stream, se for imagem, apenas retornamos o arquivo
        $mime = mime_content_type($fullPath);

        if (str_contains($mime, 'video')) {
            // Para vídeos (MP4, etc)
            return response()->file($fullPath, [
                'Content-Type' => $mime,
                'Accept-Ranges' => 'bytes'
            ]);
        }

        // Para fotos
        return response()->file($fullPath);
    }

    // #################################################################################
    // Funções para controlar o serviço de hash (start/stop/status)
    // #################################################################################
    public function update(Request $request, ServiceControl $serviceControl)
    {
        $result = $serviceControl->handle('MediaHashService', $request->action);
        Log::info("Ação de serviço: " . $request->action . " Resultado: " . json_encode($result));
        return response()->json($result);
    }


    public function startStopService($name, $action)
    {
        Log::info("Requisição para {$action} o serviço: {$name}");
        // 1. Validar a ação
        if (!in_array($action, ['start', 'stop'])) {
            return response()->json(['success' => false, 'message' => 'Ação inválida.'], 400);
        }

        // 2. Lista de serviços permitidos (Segurança)
        $allowedServices = ['MediaHashService'];
        if (!in_array($name, $allowedServices)) {
            return response()->json(['success' => false, 'message' => 'Serviço não autorizado.'], 403);
        }

        try {
            // 3. Executar o comando SC do Windows
            // Nota: O servidor Apache/Laragon deve ter permissões de administrador
            $command = "sc {$action} \"{$name}\"";
            Log::info("Executando comando: {$command}");

            $result = Process::run($command);

            Log::info("Resultado do comando: " . $result->output());

            if ($result->successful()) {
                Log::info("Serviço {$name} alterado para {$action} com sucesso.");
                return response()->json([
                    'success' => true,
                    'message' => "Serviço {$name} recebeu o comando {$action}."
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erro de permissão ou serviço inexistente: ' . $result->errorOutput()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

}
