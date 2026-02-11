# comandos uteis

No power shell 
(Get-ChildItem -File -Recurse).Count 

listar sem subpastas em qq lugar do nome:
Get-ChildItem -Path "d*" -File

lista o que começa no inicio do nome
nome:~<d tipo:NOT pasta


galeria-origem-nome-description-file_id

{galeria}-{origem}-{nome}-{descricao}-{id}.{extensao}

'photo_name'    => $validatedData['photo_name'],
        'photo_gallery' => $validatedData['photo_gallery'],
        'description'   => $validatedData['description'],
        'origin'        => $validatedData['origin'],
        'private'       => $validatedData['private'],


        php artisan queue:work --timeout=300


        -- Usar CASCADE para apagar automaticamente registos em face_matches
-- que dependam dos registos na tabela faces.
TRUNCATE TABLE faces RESTART IDENTITY CASCADE;

-- Resetar o status na tabela de ficheiros
UPDATE media_files SET face_scanned = false WHERE face_scanned = true;


# CRIAR TOKEN PARA API
$user = App\Models\User::first();
$token = $user->createToken('media-service-local')->plainTextToken;
$token;

# migrar legado do postgress para o mysql
php artisan migrate:legacy-data

#acertar storage link
php artisan storage:link
rm public/storage