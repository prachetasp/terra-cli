<?php

namespace terra\Command\Environment;

use terra\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;

use terra\Factory\EnvironmentFactory;

// ...

class EnvironmentAdd extends Command
{
  protected function configure()
  {
    $this
      ->setName('environment:add')
      ->setDescription('Adds a new environment.')
      ->addArgument(
        'app_name',
        InputArgument::OPTIONAL,
        'The app you would like to add an environment for.'
      )
      ->addArgument(
        'environment_name',
        InputArgument::OPTIONAL,
        'The name of the environment.'
      )
      ->addArgument(
        'path',
        InputArgument::OPTIONAL,
        'The path to the environment.'
      )
      ->addArgument(
        'document_root',
        InputArgument::OPTIONAL,
        'The path to the web document root within the repository.',
        '/'
      )
      ->addOption(
        'init-environment',
        '',
        InputArgument::OPTIONAL,
        'Clone and initiate this environment.'
      )
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    // Ask for an app.
    $helper = $this->getHelper('question');
    $this->getApp($input, $output);

    // Ask for environment name
    $environment_name = $input->getArgument('environment_name');
    while (empty($environment_name) || isset($this->app->environments[$environment_name])) {
      $question = new Question('Environment name? ');
      $environment_name = $helper->ask($input, $output, $question);

      // Look for environment with this name
      if (isset($this->app->environments[$environment_name])) {
        $output->writeln("<error> ERROR </error> Environment <comment>{$environment_name}</comment> already exists in app <comment>{$this->app->name}</comment>");
      }
    }

    // Path
    $path = $input->getArgument('path');
    if (empty($path)) {
      $config_path = $this->getApplication()->getTerra()->getConfig()->get('apps_basepath');
      $default_path = realpath($config_path) . '/' . $this->app->name . '/' . $environment_name;
      $question = new Question("Path: ($default_path) ", $default_path);
      $path = $helper->ask($input, $output, $question);
      if (empty($path)) {
        $path = $default_path;
      }
    }

    // Check for path
    $fs = new Filesystem();
    if (!$fs->isAbsolutePath($path)) {
      $path = getcwd() . '/' . $path;
    }

    // Environment object
    $environment = array(
      'name' => $environment_name,
      'path' => $path,
      'document_root' => '',
      'url' => '',
      'version' => '',
    );

    // Prepare the environment factory.
    // Clone the apps source code to the desired path.
    $environmentFactory = new EnvironmentFactory($environment, $this->app);

    // Save environment to config.
    if ($environmentFactory->init($path)) {

      // Load config from file.
      $environmentFactory->getConfig();
      $environment['document_root'] = isset($environmentFactory->config['document_root'])? $environmentFactory->config['document_root']: '';

      // Save current branch
      $environment['version'] = $environmentFactory->getRepo()->getCurrentBranch();

      // Save to registry.
      $this->getApplication()->getTerra()->getConfig()->add('apps', array($this->app->name, 'environments', $environment_name), $environment);
      $this->getApplication()->getTerra()->getConfig()->save();

      $output->writeln('<info>Environment saved to registry.</info>');
    }
    else {
      $output->writeln('<error>Unable to clone repository. Check app settings and try again.</error>');
    }


  }
}