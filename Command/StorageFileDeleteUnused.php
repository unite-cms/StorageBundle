<?php
/**
 * Created by PhpStorm.
 * User: franzwilding
 * Date: 13.02.18
 * Time: 11:37
 */

namespace UnitedCMS\StorageBundle\Command;

use Aws\S3\S3Client;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StorageFileDeleteUnused extends Command
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * {@inheritdoc}
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
          ->setName('united:storage:delete-unused')
          ->setDescription('Deletes all files from a given s3 bucket, that are not referenced by a file field.')
          ->addOption('force', InputOption::VALUE_OPTIONAL);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $buckets = [];

        $this->em->getFilters()->disable('gedmo_softdeleteable');

        // Find all content file fields.
        foreach($this->em->getRepository('UnitedCMSCoreBundle:ContentTypeField')->findBy([
          'type' => 'file',
        ]) as $field) {

            $bucket_path = $field->getSettings()->bucket['endpoint'] . '/' . $field->getSettings()->bucket['bucket'];

            if(!isset($buckets[$bucket_path])) {
                $buckets[$field->getSettings()->bucket['endpoint'] . '/' . $field->getSettings()->bucket['bucket']] = [
                    'endpoint' => $field->getSettings()->bucket['endpoint'],
                    'bucket' => $field->getSettings()->bucket['bucket'],
                    'credentials' => [
                        'key' => $field->getSettings()->bucket['key'],
                        'secret' => $field->getSettings()->bucket['secret'],
                    ],
                    'files' => [],
                ];
            }

            foreach ($this->em->getRepository('UnitedCMSCoreBundle:Content')->findBy([
              'contentType' => $field->getContentType(),
            ]) as $content) {

                if(!empty($content->getData()[$field->getIdentifier()])) {
                    $buckets[$bucket_path]['files'][] = $content->getData()[$field->getIdentifier()]['id'] . '/' . $content->getData()[$field->getIdentifier()]['name'];
                }
            }
        }

        // Find all setting file fields.
        foreach($this->em->getRepository('UnitedCMSCoreBundle:SettingTypeField')->findBy([
          'type' => 'file',
        ]) as $field) {

            $bucket_path = $field->getSettings()->bucket['endpoint'] . '/' . $field->getSettings()->bucket['bucket'];

            if(!isset($buckets[$bucket_path])) {
                $buckets[$field->getSettings()->bucket['endpoint'] . '/' . $field->getSettings()->bucket['bucket']] = [
                  'endpoint' => $field->getSettings()->bucket['endpoint'],
                  'bucket' => $field->getSettings()->bucket['bucket'],
                  'credentials' => [
                    'key' => $field->getSettings()->bucket['key'],
                    'secret' => $field->getSettings()->bucket['secret'],
                  ],
                  'files' => [],
                ];
            }

            foreach ($this->em->getRepository('UnitedCMSCoreBundle:Setting')->findBy([
              'settingType' => $field->getSettingType(),
            ]) as $setting) {

                if(!empty($setting->getData()[$field->getIdentifier()])) {
                    $buckets[$bucket_path]['files'][] = $setting->getData()[$field->getIdentifier()]['id'] . '/' . $setting->getData()[$field->getIdentifier()]['name'];
                }
            }
        }

        $this->em->getFilters()->enable('gedmo_softdeleteable');

        $output->writeln(['', '', '', 'The following files are in use:']);

        foreach($buckets as $bucket => $config) {
            $output->writeln(['','<bg=green;fg=white>' . $bucket . '</>']);

            foreach($config['files'] as $file) {
                $output->writeln(['     ' . $file]);
            }
        }

        $output->writeln(['', '', '', 'The following files are not in use and will be deleted if you pass --force:']);

        foreach($buckets as $bucket => $config) {
            $output->writeln(['','<bg=red;fg=white>' . $bucket . '</>']);

            $objects_to_delete = [];

            // Return pre-signed url
            $s3Client = new S3Client([
              'version' => 'latest',
              'region'  => 'us-east-1',
              'endpoint' => $config['endpoint'],
              'use_path_style_endpoint' => true,
              'credentials' => [
                'key'    => $config['credentials']['key'],
                'secret' => $config['credentials']['secret'],
              ],
            ]);

            foreach($s3Client->getIterator('ListObjects', array('Bucket' => $config['bucket'])) as $object) {
                if(!in_array($object['Key'], $config['files'])) {
                    $objects_to_delete[] = ['Key' => $object['Key']];
                    $output->writeln(['     ' . $object['Key']]);
                }
            }

            if($input->getOption('force')) {

                $s3Client->deleteObjects([
                    'Bucket'  => $config['bucket'],
                    'Delete' => [
                        'Objects' => $objects_to_delete,
                    ],
                ]);

                $output->writeln([
                  '',
                  '',
                  '<bg=black;fg=white;options=bold>*****************************************</>',
                  '<bg=black;fg=white;options=bold>         ' . count($objects_to_delete) . ' files where deleted.         </>',
                  '<bg=black;fg=white;options=bold>*****************************************</>'
                ]);
            }
        }

        $output->writeln(['']);
    }
}