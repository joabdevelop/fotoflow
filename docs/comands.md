senha da API
Token do Joabe: 2|gFw8Y2QcgxsBmxJd6tNYne8Woq4dmcAwnnf2moE5b4edd2a4


# CRIAR TOKEN PARA API
$user = App\Models\User::first();
$token = $user->createToken('media-service-local')->plainTextToken;
$token;


#acertar storage link
php artisan storage:link
rm public/storage

##***************************************************************
# Comandos para versionamento com Git
# Verifica quais arquivos foram alterados git status

# Adiciona todas as mudanças (ou use 'git add nome_do_arquivo' para ser seletivo)
git add .

# Cria o ponto de salvamento com uma descrição clara
git commit -m "feat: implementa processamento de faces, exif"

# Envia para o servidor do GitHub
git push origin main

##***************************************************************
# Se o repositório ainda não estiver configurado, use os seguintes comandos para inicializar e conectar ao GitHub
git init
git remote add origin https://github.com/seu-usuario/fotoflow.git
git branch -M main
git push -u origin main