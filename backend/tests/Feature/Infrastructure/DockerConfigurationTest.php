<?php

namespace Tests\Feature\Infrastructure;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class DockerConfigurationTest extends TestCase
{
    public function test_php_upload_policy_accepts_the_application_image_limit(): void
    {
        $configuration = parse_ini_file(base_path('docker/php/uploads.ini'));

        $this->assertIsArray($configuration);
        $this->assertSame('9M', $configuration['upload_max_filesize']);
        $this->assertSame('10M', $configuration['post_max_size']);
        $this->assertSame('256M', $configuration['memory_limit']);
    }

    public function test_compose_orders_postgres_migrations_and_runtime_services(): void
    {
        $compose = Yaml::parseFile(base_path('compose.yaml'));
        $services = $compose['services'];

        $this->assertEqualsCanonicalizing(
            ['web', 'app', 'worker', 'migrate', 'db', 'php-tests', 'js-tests'],
            array_keys($services),
        );
        $this->assertSame('postgres:17-alpine', $services['db']['image']);
        $this->assertArrayNotHasKey('ports', $services['db']);
        $this->assertSame('service_healthy', $services['migrate']['depends_on']['db']['condition']);
        $this->assertSame('service_completed_successfully', $services['app']['depends_on']['migrate']['condition']);
        $this->assertSame('service_completed_successfully', $services['worker']['depends_on']['migrate']['condition']);
        $this->assertContains('queue:work', $services['worker']['command']);
        $this->assertSame(['test'], $services['php-tests']['profiles']);
        $this->assertSame(['test'], $services['js-tests']['profiles']);
    }

    public function test_docker_environment_template_keeps_credentials_empty(): void
    {
        $environment = parse_ini_file(base_path('.env.docker.example'));

        $this->assertIsArray($environment);
        $this->assertSame('', $environment['APP_KEY']);
        $this->assertSame('', $environment['DB_PASSWORD']);
        $this->assertSame('', $environment['GEMINI_API_KEY']);
    }

    public function test_staging_overlay_adds_tls_and_safe_laravel_defaults(): void
    {
        $staging = Yaml::parseFile(
            base_path('compose.staging.yaml'),
            Yaml::PARSE_CUSTOM_TAGS,
        );
        $services = $staging['services'];

        $this->assertSame('caddy:2-alpine', $services['caddy']['image']);
        $this->assertContains('80:80', $services['caddy']['ports']);
        $this->assertContains('443:443', $services['caddy']['ports']);
        $this->assertContains('443:443/udp', $services['caddy']['ports']);
        $this->assertSame('staging', $services['app']['environment']['APP_ENV']);
        $this->assertSame('false', $services['app']['environment']['APP_DEBUG']);
        $this->assertSame('true', $services['app']['environment']['SESSION_SECURE_COOKIE']);
        $this->assertStringStartsWith('https://', $services['app']['environment']['APP_URL']);
    }
}
