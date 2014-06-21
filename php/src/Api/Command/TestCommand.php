<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('person:show')
            ->setDescription('Show Person by id')
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'id of the Person'
            )
            /*
            ->addOption(
               'yell',
               null,
               InputOption::VALUE_NONE,
               'If set, the task will yell in uppercase letters'
            ) */
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $id = $input->getArgument('id');

        $container = $this->getApplication()->getContainer();
        $personService = $container['person.service'];

        $output->writeln(json_encode($personService->getOne($id)));
    }
}
