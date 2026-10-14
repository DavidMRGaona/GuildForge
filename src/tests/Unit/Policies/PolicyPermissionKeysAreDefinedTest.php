<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Infrastructure\Authorization\CorePermissionDefinitions;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A permission that a policy checks but nobody defines never reaches the permissions
 * table: only admins pass it and no role can be granted it.
 */
final class PolicyPermissionKeysAreDefinedTest extends TestCase
{
    public function test_every_permission_checked_by_a_core_policy_or_panel_page_is_defined(): void
    {
        $defined = array_map(static fn ($definition): string => $definition->key, CorePermissionDefinitions::all());
        $checked = $this->checkedKeys();

        $this->assertNotEmpty($checked);
        $this->assertSame([], array_values(array_diff($checked, $defined)), 'Permissions checked but not defined in CorePermissionDefinitions');
    }

    /**
     * @return array<int, string>
     */
    private function checkedKeys(): array
    {
        $keys = [];

        foreach (File::allFiles(app_path('Policies')) as $file) {
            $source = $file->getContents();

            preg_match_all('/->authorize\(\s*\$\w+\s*,\s*\'([^\']+)\'/', $source, $single);
            preg_match_all('/->authorize(?:Any|All)\(\s*\$\w+\s*,\s*\[([^\]]*)\]/', $source, $lists);
            $keys = [...$keys, ...$single[1]];

            foreach ($lists[1] as $list) {
                preg_match_all('/\'([^\']+)\'/', $list, $listed);
                $keys = [...$keys, ...$listed[1]];
            }
        }

        foreach (File::allFiles(app_path('Filament')) as $file) {
            preg_match_all('/userCan\(\s*\'([^\']+)\'/', $file->getContents(), $pages);
            $keys = [...$keys, ...$pages[1]];
        }

        return array_values(array_unique($keys));
    }
}
