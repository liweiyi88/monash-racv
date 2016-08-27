<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class RacvADCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('racv:ad-dd')
            ->addOption('PAR_ID',null,InputOption::VALUE_OPTIONAL)
            ->addOption('VEH_ID',null,InputOption::VALUE_OPTIONAL)
            ->setDescription('Appending AD DD');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $conn = $this->getContainer()->get('doctrine')->getConnection();

        $query = "SELECT `PAR_ID`,`VEH_REG_ID`, `DESCN_1_TXT`, max(ST_DTE) as `new_st`, min(`ST_DTE`) as `old_st`, max(`CANCD_DTE`) as `new_can` FROM `c_e` GROUP BY `PAR_ID`,`VEH_REG_ID`";
        $dateCollection = $conn->fetchAll($query);

        $count = (count($dateCollection));
        $output->setVerbosity($output::VERBOSITY_DEBUG);
        $progress = new ProgressBar($output, $count);

        $output->writeln([
            'Inserting data',
            '============',
            '',
        ]);


        foreach ($dateCollection as $data) {
            $parId = $data['PAR_ID'];
            $vehId = $data['VEH_REG_ID'];
            $modelYear = $data['DESCN_1_TXT'];
            $newestStartDate = $data['new_st'];
            $oldestStartDate = $data['old_st'];
            $newestCancellationDate = $data['new_can'];

            $ad = $oldestStartDate;
            if (strtotime($newestCancellationDate) > strtotime($newestStartDate)) {
                $dd = $newestCancellationDate;
            } else {
                $dd = '2100-01-01';
            }

            $stmt = $conn->prepare("INSERT INTO `union_ad_dd` (`PAR_ID`,`VEH_REG_ID`,`AD`,`DD`,`model_year`) VALUES ( :parId, :vehId, :ad, :dd, :modelYear)");
            $stmt->execute(array(
                ':parId' => $parId,
                ':vehId' => $vehId,
                ':ad' => $ad,
                ':dd' => $dd,
                ':modelYear' => $modelYear
            ));

            $progress->advance();
        }
        $progress->finish();
    }
}
