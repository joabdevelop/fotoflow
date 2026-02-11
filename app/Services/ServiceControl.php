<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Classe responsável por interagir com o Service Control Manager do Windows.
 */
class ServiceControl
{
    /**
     * Verifica o estado atual de um serviço.
     * * @param string $serviceName Nome técnico do serviço (ex: MediaHashService)
     * @return string 'running', 'stopped' ou 'error'
     */
    public function getStatus(string $serviceName): string
    {
        // Comando sc query para verificar estado
        $output = shell_exec("sc query \"$serviceName\"");

        if (str_contains($output, 'RUNNING')) {
            return 'running';
        } elseif (str_contains($output, 'STOPPED')) {
            return 'stopped';
        }

        return 'error';
    }

    /**
     * Inicia ou para um serviço.
     * * @param string $serviceName
     * @param string $action 'start' ou 'stop'
     * @return array
     */
    public function handle(string $serviceName, string $action): array
    {
        // Validação básica de acção para evitar injeção de comandos
        if (!in_array($action, ['start', 'stop'])) {
            return ['success' => false, 'message' => 'Acção inválida.'];
        }

        // Execução do comando (Requer privilégios de administrador ou permissões SCM)
        $command = "sc $action \"$serviceName\"";
        Log::info("Executando comando: $command");
        $output = shell_exec($command);

        Log::info("Serviço $serviceName: $action executado. Saída: " . $output);

        return [
            'success' => str_contains($output, 'PENDING') || str_contains($output, 'SUCCESS'),
            'output' => trim($output),
            'new_status' => $action === 'start' ? 'running' : 'stopped'
        ];
    }
}