<?php

declare(strict_types=1);

namespace App\Domain\Account\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Apaga a conta do usuário e todas as informações pessoais vinculadas (LGPD art. 18 VI).
 *
 * Depende de FKs com `cascadeOnDelete` nas migrations — apenas arquivos em storage
 * (PDFs de currículos) precisam ser removidos manualmente.
 */
final class DeleteAccount
{
    public function execute(User $user): void
    {
        // Limpa arquivos em storage antes de apagar (cascade na FK cuida das linhas,
        // mas não dos blobs em S3/MinIO).
        foreach ($user->resumes as $resume) {
            if ($resume->file_path !== null && $resume->file_path !== '') {
                Storage::disk('s3')->delete($resume->file_path);
            }
        }

        // Spatie MediaLibrary: remove anexos de candidaturas.
        foreach ($user->applications as $application) {
            $application->clearMediaCollection($application::ATTACHMENT_COLLECTION);
        }

        // Sanctum: invalida tokens pessoais antes de deslogar (só se a tabela existir —
        // a migration é opcional e publicada sob demanda).
        if (Schema::hasTable('personal_access_tokens')) {
            $user->tokens()->delete();
        }

        // Desloga ANTES de apagar: Auth::logout() cicla o remember_token via save(),
        // o que reinseriria o usuário se o delete viesse antes.
        Auth::guard('web')->logout();

        $user->delete();
    }
}
