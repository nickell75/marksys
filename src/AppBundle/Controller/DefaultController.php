<?php

namespace AppBundle\Controller;

use AppBundle\Entity\CsvCell;
use AppBundle\Entity\CsvFile;
use AppBundle\Form\CsvUploadForm;
use AppBundle\Model\CsvUploadModel;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction()
    {
        $em = $this->get('doctrine.orm.default_entity_manager');

        $csvFiles = $em->getRepository(CsvFile::class)->findAll();

        return $this->render('@App/index.html.twig', [
            'files' => $csvFiles,
        ]);
    }

    /**
     * @Route("/upload", name="upload_file")
     */
    public function uploadAction(Request $request)
    {
        $model = new CsvUploadModel();
        $form = $this->createForm(CsvUploadForm::class, $model);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (($handle = fopen($model->getFile()->getRealPath(), 'r')) !== false) {
                $rowKey = 1;
                $csvFile = new CsvFile();
                $csvFile->setName($model->getFile()->getClientOriginalName());
                while (($row = fgetcsv($handle, 0, ",")) !== false) {
                    foreach ($row as $cellKey => $cellValue) {
                        $csvFile->addCell(CsvCell::createCell($rowKey, $cellKey + 1, $cellValue));
                    }
                    $rowKey++;
                }
                fclose($handle);

                $em = $this->get('doctrine.orm.default_entity_manager');
                try {
                    $em->persist($csvFile);
                    $em->flush();
                } catch (\Exception $exception) {
                    throw new \RuntimeException($exception->getMessage());
                }
            } else {
                throw new \RuntimeException('could not open file for reading');
            }

            return $this->redirect($this->generateUrl('homepage'));
        }

        return $this->render('@App/upload.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/view/{uuid}", name="view_file")
     */
    public function viewFileAction($uuid)
    {
        $em = $this->get('doctrine.orm.default_entity_manager');

        /** @var CsvFile $csvFile */
        $csvFile = $em->getRepository(CsvFile::class)->getFile($uuid);
        if (is_null($csvFile)) {
            throw $this->createNotFoundException();
        }

        return $this->render('@App/view.html.twig', [
            'file' => $csvFile
        ]);
    }
}
