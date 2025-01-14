<?php

namespace Sunspikes\Tests\ClamavValidator;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Facade;
use Mockery;
use Illuminate\Contracts\Translation\Translator;
use PHPUnit\Framework\TestCase;
use Sunspikes\ClamavValidator\ClamavValidator;
use Sunspikes\ClamavValidator\ClamavValidatorException;

class ClamavValidatorTest extends TestCase
{
    protected $translator;
    protected $clean_data;
    protected $virus_data;
    protected $error_data;
    protected $rules;
    protected $messages;
    protected $multiple_files_all_clean;
    protected $multiple_files_some_with_virus;

    public function setUp(): void
    {
        $this->translator = Mockery::mock(Translator::class);
        $this->translator->shouldReceive('get')->with('validation.custom.file.clamav')->andReturn('error');
        $this->translator->shouldReceive('get')->withAnyArgs()->andReturn('');
        $this->translator->shouldReceive('get')->with('validation.attributes')->andReturn([]);
        $this->translator->shouldReceive('trans');
        $this->clean_data = [
            'file' => $this->getTempPath(__DIR__ . '/files/test1.txt')
        ];
        $this->virus_data = [
            'file' => $this->getTempPath(__DIR__ . '/files/test2.txt')
        ];
        $this->error_data = [
            'file' => $this->getTempPath(__DIR__ . '/files/test3.txt')
        ];
        $this->multiple_files_all_clean = [
        	'files' => [
				$this->getTempPath(__DIR__ . '/files/test1.txt'),
				$this->getTempPath(__DIR__ . '/files/test4.txt'),
			]
		];
        $this->multiple_files_some_with_virus = [
			'files' => [
				$this->getTempPath(__DIR__ . '/files/test1.txt'),
				$this->getTempPath(__DIR__ . '/files/test2.txt'),
				$this->getTempPath(__DIR__ . '/files/test4.txt'),
			]
		];
        $this->messages = [];

        $config = new Config();
        $config->shouldReceive('get')->with('clamav.preferred_socket')->andReturn('unix_socket');
        $config->shouldReceive('get')->with('clamav.unix_socket')->andReturn('/var/run/clamav/clamd.ctl');
        $config->shouldReceive('get')->with('clamav.tcp_socket')->andReturn('tcp://127.0.0.1:3310');
        $config->shouldReceive('get')->with('clamav.socket_read_timeout')->andReturn(30);
        $config->shouldReceive('get')->with('clamav.skip_validation')->andReturn(false);

        $application = Mockery::mock(Application::class, ['make' => $config]);
        Facade::setFacadeApplication($application);
    }

    public function tearDown(): void
    {
        chmod($this->error_data['file'], 0644);
        Mockery::close();
    }

    public function testValidatesClean()
    {
        $validator = new ClamavValidator(
            $this->translator,
            $this->clean_data,
            ['file' => 'clamav'],
            $this->messages
        );

        $this->assertTrue($validator->passes());
    }

	public function testValidatesCleanMultiFile()
	{
		$validator = new ClamavValidator(
			$this->translator,
			$this->multiple_files_all_clean,
			['files' => 'clamav'],
			$this->messages
		);

		$this->assertTrue($validator->passes());
	}

    public function testValidatesVirus()
    {
        $validator = new ClamavValidator(
            $this->translator,
            $this->virus_data,
            ['file' => 'clamav'],
            $this->messages
        );

        $this->assertFalse($validator->passes());
    }

	public function testValidatesVirusMultiFile()
	{
		$validator = new ClamavValidator(
			$this->translator,
			$this->multiple_files_some_with_virus,
			['files' => 'clamav'],
			$this->messages
		);

		$this->assertFalse($validator->passes());
	}

    public function testValidatesError()
    {
        $this->expectException(ClamavValidatorException::class, 'is not readable');

        $validator = new ClamavValidator(
            $this->translator,
            $this->error_data,
            ['file' => 'clamav'],
            $this->messages
        );

        chmod($this->error_data['file'], 0000);

        $validator->passes();
    }

    /**
     * Move to temp dir, so that clamav can access the file
     *
     * @param $file
     * @return string
     */
    private function getTempPath($file)
    {
        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . basename($file);
        copy($file, $tempPath);
        chmod($tempPath, 0644);

        return $tempPath;
    }
}
