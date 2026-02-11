# 1. Instala as bibliotecas PHP (conforme o composer.json e lock)
composer install --optimize-autoloader --no-dev (na VPS use no-dev)

# 2. Instala as dependências de JS/CSS sem alterar seus arquivos Blade
npm install

# 3. Compila os assets (Vite) para produção
npm run build

# 4. Atualiza o banco sem mexer no código
php artisan migrate --force