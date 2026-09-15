<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Services\Parser\YandexUrlParser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parser = new YandexUrlParser;

        // cursor() вместо each(): замыкание убирает строки из whereNull-фильтра,
        // и offset-чанки each() пропускали бы записи.
        foreach (Organization::query()->whereNull('yandex_id')->orderBy('id')->cursor() as $organization) {
            try {
                $organization->yandex_id = $parser->extractOrgId($organization->yandex_url);
                $organization->save();
            } catch (InvalidArgumentException) {
            }
        }

        // Дубликаты (user_id, yandex_id): оставляем самую раннюю запись,
        // у остальных обнуляем yandex_id, иначе CREATE UNIQUE INDEX упадёт.
        DB::statement(<<<'SQL'
            UPDATE organizations SET yandex_id = NULL
            WHERE id NOT IN (
                SELECT MIN(id) FROM organizations
                WHERE yandex_id IS NOT NULL
                GROUP BY user_id, yandex_id
            )
            AND yandex_id IS NOT NULL
        SQL);

        DB::statement('CREATE UNIQUE INDEX organizations_user_id_yandex_id_unique ON organizations (user_id, yandex_id) WHERE yandex_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS organizations_user_id_yandex_id_unique');
    }
};
