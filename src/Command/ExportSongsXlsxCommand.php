<?php

namespace App\Command;

use App\Repository\SongKeywordRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:export-songs-xlsx', description: 'Export songs without Etikett to an XLSX file')]
class ExportSongsXlsxCommand extends Command
{
    public function __construct(private SongKeywordRepository $repo)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file path', '/tmp/songs_export.xlsx');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $songs = $this->repo->createQueryBuilder('s')
            ->leftJoin('s.styles', 'st')
            ->addSelect('st')
            ->leftJoin('s.parent', 'p')
            ->where('s.etikett IS NULL OR s.etikett = :empty')
            ->setParameter('empty', '')
            ->orderBy('COALESCE(p.songName, s.songName)', 'ASC')
            ->addOrderBy('s.songName', 'ASC')
            ->getQuery()
            ->getResult();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Songs');

        $cols = ['A', 'B', 'C', 'D'];
        $headers = ['Titel', 'Komponist', 'Übergeordneter Titel', 'Stile'];
        foreach ($headers as $i => $header) {
            $cell = $cols[$i] . '1';
            $sheet->setCellValue($cell, $header);
            $sheet->getColumnDimension($cols[$i])->setAutoSize(true);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }

        $row = 2;
        foreach ($songs as $song) {
            $styles = $song->getStyles()->map(fn($s) => $s->getName())->toArray();

            $sheet->setCellValue('A' . $row, $song->getSongName());
            $sheet->setCellValue('B' . $row, $song->getComposer());
            $sheet->setCellValue('C' . $row, $song->getParent()?->getSongName());
            $sheet->setCellValue('D' . $row, implode(', ', $styles));
            $row++;
        }

        $path = $input->getOption('output');
        (new Xlsx($spreadsheet))->save($path);

        $output->writeln(sprintf('<info>Exported %d songs to %s</info>', $row - 2, $path));

        return Command::SUCCESS;
    }
}
