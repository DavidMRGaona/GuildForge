<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Modules\Services;

use App\Infrastructure\Modules\Services\StubRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class StubRendererTest extends TestCase
{
    private StubRenderer $renderer;

    private string $stubsPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubsPath = dirname(__DIR__, 5).'/tests/Fixtures/stubs';
        $this->renderer = new StubRenderer($this->stubsPath);
    }

    public function test_it_renders_stub_with_variables(): void
    {
        $variables = [
            'moduleName' => 'Blog',
            'moduleNamespace' => 'Modules\\Blog',
            'name' => 'PostService',
            'nameStudly' => 'PostService',
        ];

        $content = $this->renderer->render('test.php.stub', $variables);

        $this->assertStringContainsString('Module: Blog', $content);
        $this->assertStringContainsString('Class: PostService', $content);
        $this->assertStringContainsString('StudlyName: PostService', $content);
        $this->assertStringContainsString('namespace Modules\\Blog;', $content);
        $this->assertStringContainsString('final class PostService', $content);
    }

    public function test_it_throws_exception_when_stub_not_found(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stub file not found: nonexistent.php.stub');

        $this->renderer->render('nonexistent.php.stub', []);
    }

    public function test_it_replaces_variables_with_spaces_around_braces(): void
    {
        $variables = [
            'moduleName' => 'Forum',
            'moduleNamespace' => 'Modules\\Forum',
            'name' => 'Thread',
            'nameStudly' => 'Thread',
        ];

        $content = $this->renderer->render('test.php.stub', $variables);

        $this->assertStringNotContainsString('{{ moduleName }}', $content);
        $this->assertStringNotContainsString('{{ name }}', $content);
        $this->assertStringContainsString('Forum', $content);
        $this->assertStringContainsString('Thread', $content);
    }

    public function test_get_module_variables_returns_correct_variables(): void
    {
        $variables = $this->renderer->getModuleVariables('blog-posts', 'A blog module', 'John Doe');

        $this->assertEquals('blog-posts', $variables['moduleName']);
        $this->assertEquals('BlogPosts', $variables['moduleNameStudly']);
        $this->assertEquals('blog_posts', $variables['moduleNameSnake']);
        $this->assertEquals('blogPosts', $variables['moduleNameCamel']);
        $this->assertEquals('Modules\\BlogPosts', $variables['moduleNamespace']);
        $this->assertEquals('Modules\\\\BlogPosts', $variables['moduleNamespaceJson']);
        $this->assertEquals('A blog module', $variables['moduleDescription']);
        $this->assertEquals('John Doe', $variables['moduleAuthor']);
        $this->assertArrayHasKey('timestamp', $variables);
    }

    public function test_get_module_variables_handles_null_description_and_author(): void
    {
        $variables = $this->renderer->getModuleVariables('events');

        $this->assertEquals('', $variables['moduleDescription']);
        $this->assertEquals('', $variables['moduleAuthor']);
    }

    public function test_get_component_variables_includes_module_and_component_variables(): void
    {
        $variables = $this->renderer->getComponentVariables('forum', 'Topic');

        $this->assertEquals('forum', $variables['moduleName']);
        $this->assertEquals('Forum', $variables['moduleNameStudly']);
        $this->assertEquals('Modules\\Forum', $variables['moduleNamespace']);
        $this->assertEquals('Modules\\\\Forum', $variables['moduleNamespaceJson']);
        $this->assertEquals('Topic', $variables['name']);
        $this->assertEquals('Topic', $variables['nameStudly']);
        $this->assertEquals('topic', $variables['nameSnake']);
        $this->assertEquals('topic', $variables['nameCamel']);
        $this->assertEquals('Topics', $variables['namePlural']);
        $this->assertEquals('Topics', $variables['namePluralStudly']);
        $this->assertEquals('topics', $variables['namePluralSnake']);
        $this->assertEquals('topic', $variables['nameKebab']);
        $this->assertEquals('forum_topics', $variables['tableName']);
    }

    public function test_get_component_variables_handles_complex_names(): void
    {
        $variables = $this->renderer->getComponentVariables('user-management', 'UserProfile');

        $this->assertEquals('UserProfile', $variables['nameStudly']);
        $this->assertEquals('user_profile', $variables['nameSnake']);
        $this->assertEquals('userProfile', $variables['nameCamel']);
        $this->assertEquals('UserProfiles', $variables['namePluralStudly']);
        $this->assertEquals('user_profiles', $variables['namePluralSnake']);
        $this->assertEquals('user-profile', $variables['nameKebab']);
        $this->assertEquals('user_management_user_profiles', $variables['tableName']);
    }

    public function test_stub_exists_returns_true_for_existing_stub(): void
    {
        $exists = $this->renderer->stubExists('test.php.stub');

        $this->assertTrue($exists);
    }

    public function test_stub_exists_returns_false_for_nonexistent_stub(): void
    {
        $exists = $this->renderer->stubExists('nonexistent.php.stub');

        $this->assertFalse($exists);
    }

    public function test_get_stub_path_adds_stub_extension(): void
    {
        $path = $this->renderer->getStubPath('test.php');

        $this->assertStringEndsWith('test.php.stub', $path);
    }

    public function test_get_stub_path_preserves_stub_extension(): void
    {
        $path = $this->renderer->getStubPath('test.php.stub');

        $this->assertStringEndsWith('test.php.stub', $path);
        $this->assertStringNotContainsString('.stub.stub', $path);
    }

    public function test_render_to_creates_file_successfully(): void
    {
        $destination = sys_get_temp_dir().'/test-module-service.php';
        $variables = [
            'moduleName' => 'TestModule',
            'moduleNamespace' => 'Modules\\TestModule',
            'name' => 'Service',
            'nameStudly' => 'Service',
        ];

        $result = $this->renderer->renderTo('test.php.stub', $destination, $variables);

        $this->assertTrue($result);
        $this->assertFileExists($destination);

        $content = file_get_contents($destination);
        $this->assertStringContainsString('Module: TestModule', $content);
        $this->assertStringContainsString('Class: Service', $content);

        unlink($destination);
    }

    public function test_render_to_does_not_overwrite_existing_file_without_force(): void
    {
        $destination = sys_get_temp_dir().'/existing-file.php';
        file_put_contents($destination, 'Original content');

        $variables = [
            'moduleName' => 'Test',
            'moduleNamespace' => 'Modules\\Test',
            'name' => 'Class',
            'nameStudly' => 'Class',
        ];

        $result = $this->renderer->renderTo('test.php.stub', $destination, $variables, false);

        $this->assertFalse($result);
        $content = file_get_contents($destination);
        $this->assertEquals('Original content', $content);

        unlink($destination);
    }

    public function test_render_to_overwrites_existing_file_with_force(): void
    {
        $destination = sys_get_temp_dir().'/force-overwrite.php';
        file_put_contents($destination, 'Original content');

        $variables = [
            'moduleName' => 'Forced',
            'moduleNamespace' => 'Modules\\Forced',
            'name' => 'NewClass',
            'nameStudly' => 'NewClass',
        ];

        $result = $this->renderer->renderTo('test.php.stub', $destination, $variables, true);

        $this->assertTrue($result);
        $content = file_get_contents($destination);
        $this->assertStringContainsString('Module: Forced', $content);
        $this->assertStringNotContainsString('Original content', $content);

        unlink($destination);
    }

    public function test_render_to_creates_directory_if_not_exists(): void
    {
        $directory = sys_get_temp_dir().'/nested/deep/path';
        $destination = $directory.'/test-file.php';

        $variables = [
            'moduleName' => 'Deep',
            'moduleNamespace' => 'Modules\\Deep',
            'name' => 'NestedClass',
            'nameStudly' => 'NestedClass',
        ];

        $result = $this->renderer->renderTo('test.php.stub', $destination, $variables);

        $this->assertTrue($result);
        $this->assertFileExists($destination);
        $this->assertDirectoryExists($directory);

        unlink($destination);
        rmdir($directory);
        rmdir(dirname($directory));
        rmdir(dirname(dirname($directory)));
    }

    public function test_it_handles_empty_variables_array(): void
    {
        $content = $this->renderer->render('test.php.stub', []);

        $this->assertStringContainsString('{{ moduleName }}', $content);
        $this->assertStringContainsString('{{ name }}', $content);
        $this->assertStringContainsString('{{ nameStudly }}', $content);
    }

    public function test_replace_variables_supports_both_spacing_formats(): void
    {
        $variables = [
            'moduleName' => 'SpacingTest',
            'moduleNamespace' => 'Modules\\SpacingTest',
            'name' => 'TestClass',
            'nameStudly' => 'TestClass',
        ];

        $content = $this->renderer->render('test.php.stub', $variables);

        $this->assertStringNotContainsString('{{moduleName}}', $content);
        $this->assertStringNotContainsString('{{ moduleName }}', $content);
        $this->assertStringContainsString('SpacingTest', $content);
    }
}
