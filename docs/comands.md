senha da API
Token do Joabe: 2|gFw8Y2QcgxsBmxJd6tNYne8Woq4dmcAwnnf2moE5b4edd2a4


# CRIAR TOKEN PARA API
$user = App\Models\User::first();
$token = $user->createToken('media-service-local')->plainTextToken;
$token;


#acertar storage link
php artisan storage:link
rm public/storage