<?php
echo "Usuário do processo: " . get_current_user() . "<br>";
echo "Dono do script: " . gethostname() . "\\" . exec('whoami');