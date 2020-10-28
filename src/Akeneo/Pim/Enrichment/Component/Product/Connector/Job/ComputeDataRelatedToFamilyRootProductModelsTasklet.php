<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Component\Product\Connector\Job;

use Akeneo\Pim\Enrichment\Component\Product\EntityWithFamilyVariant\KeepOnlyValuesForVariation;
use Akeneo\Pim\Enrichment\Component\Product\Model\EntityWithFamilyVariantInterface;
use Akeneo\Pim\Enrichment\Component\Product\Query\Filter\Operators;
use Akeneo\Pim\Enrichment\Component\Product\Query\ProductQueryBuilderFactoryInterface;
use Akeneo\Pim\Structure\Component\Model\FamilyInterface;
use Akeneo\Tool\Component\Batch\Item\InitializableInterface;
use Akeneo\Tool\Component\Batch\Item\InvalidItemException;
use Akeneo\Tool\Component\Batch\Item\ItemReaderInterface;
use Akeneo\Tool\Component\Batch\Item\TrackableTaskletInterface;
use Akeneo\Tool\Component\Batch\Job\JobRepositoryInterface;
use Akeneo\Tool\Component\Batch\Model\StepExecution;
use Akeneo\Tool\Component\Connector\Step\TaskletInterface;
use Akeneo\Tool\Component\StorageUtils\Cache\EntityManagerClearerInterface;
use Akeneo\Tool\Component\StorageUtils\Cursor\CursorInterface;
use Akeneo\Tool\Component\StorageUtils\Repository\IdentifiableObjectRepositoryInterface;
use Akeneo\Tool\Component\StorageUtils\Saver\BulkSaverInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * For each line of the file of families to import we will:
 * - fetch the corresponding family object,
 * - fetch all the root product models of this family,
 * - batch save these product models
 *
 * This way, on family import, the family's root product models data will be
 * computed and all family variant's corresponding attributes will be indexed.
 *
 * @author    Damien Carcel <damien.carcel@akeneo.com>
 * @copyright 2018 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ComputeDataRelatedToFamilyRootProductModelsTasklet implements TaskletInterface, InitializableInterface, TrackableTaskletInterface
{
    /** @var StepExecution */
    private $stepExecution;

    /** @var IdentifiableObjectRepositoryInterface */
    private $familyRepository;

    /** @var ProductQueryBuilderFactoryInterface */
    private $queryBuilderFactory;

    /** @var ItemReaderInterface */
    private $familyReader;

    /** @var KeepOnlyValuesForVariation */
    private $keepOnlyValuesForVariation;

    /** @var ValidatorInterface */
    private $validator;

    /** @var BulkSaverInterface */
    private $productModelSaver;

    /** @var EntityManagerClearerInterface */
    private $cacheClearer;

    /** @var JobRepositoryInterface */
    private $jobRepository;

    /** @var int */
    private $batchSize;

    /**
     * @param IdentifiableObjectRepositoryInterface $familyRepository
     * @param ProductQueryBuilderFactoryInterface   $queryBuilderFactory
     * @param ItemReaderInterface                   $familyReader
     * @param KeepOnlyValuesForVariation            $keepOnlyValuesForVariation
     * @param ValidatorInterface                    $validator
     * @param BulkSaverInterface                    $productModelSaver
     * @param EntityManagerClearerInterface         $cacheClearer
     * @param JobRepositoryInterface                $jobRepository
     * @param int                                   $batchSize
     */
    public function __construct(
        IdentifiableObjectRepositoryInterface $familyRepository,
        ProductQueryBuilderFactoryInterface $queryBuilderFactory,
        ItemReaderInterface $familyReader,
        KeepOnlyValuesForVariation $keepOnlyValuesForVariation,
        ValidatorInterface $validator,
        BulkSaverInterface $productModelSaver,
        EntityManagerClearerInterface $cacheClearer,
        JobRepositoryInterface $jobRepository,
        int $batchSize
    ) {
        $this->familyRepository = $familyRepository;
        $this->queryBuilderFactory = $queryBuilderFactory;
        $this->familyReader = $familyReader;
        $this->keepOnlyValuesForVariation = $keepOnlyValuesForVariation;
        $this->validator = $validator;
        $this->productModelSaver = $productModelSaver;
        $this->cacheClearer = $cacheClearer;
        $this->jobRepository = $jobRepository;
        $this->batchSize = $batchSize;
    }

    /**
     * {@inheritdoc}
     */
    public function setStepExecution(StepExecution $stepExecution)
    {
        $this->stepExecution = $stepExecution;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $this->initialize();

        while (true) {
            try {
                $familyItem = $this->familyReader->read();
                if (null === $familyItem) {
                    break;
                }
            } catch (InvalidItemException $e) {
                continue;
            }

            $family = $this->familyRepository->findOneByIdentifier($familyItem['code']);
            if (null === $family) {
                $this->stepExecution->incrementSummaryInfo('skip');
                $this->stepExecution->incrementProcessedItems();

                continue;
            }

            $skippedProductModels = [];
            $productModelsToSave = [];
            $productModels = $this->getRootProductModelsForFamily([$family->getCode()]);

            foreach ($productModels as $productModel) {
                $this->keepOnlyValuesForVariation->updateEntitiesWithFamilyVariant([$productModel]);

                if (!$this->isValid($productModel)) {
                    $this->stepExecution->incrementSummaryInfo('skip');
                    $this->stepExecution->incrementProcessedItems();

                    $skippedProductModels[] = $productModel;
                } else {
                    $productModelsToSave[] = $productModel;
                }

                if (0 === (count($productModelsToSave) + count($skippedProductModels)) % $this->batchSize) {
                    $this->saveProductsModel($productModelsToSave);
                    $productModelsToSave = [];
                    $skippedProductModels = [];
                    $this->cacheClearer->clear();
                }
            }

            $this->saveProductsModel($productModelsToSave);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function initialize()
    {
        $this->cacheClearer->clear();
    }

    public function count(): int
    {
        return $this->computeRootProductModelsToProcess();
    }

    /**
     * @param EntityWithFamilyVariantInterface $entityWithFamilyVariant
     *
     * @return bool
     */
    private function isValid(EntityWithFamilyVariantInterface $entityWithFamilyVariant): bool
    {
        $violations = $this->validator->validate($entityWithFamilyVariant);

        return $violations->count() === 0;
    }

    /**
     * @param array $productModels
     */
    private function saveProductsModel(array $productModels): void
    {
        if (empty($productModels)) {
            return;
        }

        $this->productModelSaver->saveAll($productModels);
        $this->stepExecution->incrementSummaryInfo('process', count($productModels));
        $this->stepExecution->incrementProcessedItems(count($productModels));
        $this->jobRepository->updateStepExecution($this->stepExecution);
    }

    /**
     * Quentin raised some concerns regarding the ability to rewind the familyReader (which can be seen as a cursor)
     * in a shared environment.
     *
     * @throws \Exception
     */
    private function computeRootProductModelsToProcess(): int
    {
        $this->familyReader->rewind(); // <= especially this bit here :'(

        $familyCodes = [];
        $totalProductsToProcess = 0;
        while (true) {
            try {
                $familyItem = $this->familyReader->read();
                if (null === $familyItem) {
                    break;
                }
            } catch (InvalidItemException $e) {
                continue;
            }
            $familyCodes[] = $familyItem['code'];

            if (\count($familyCodes) % 100 === 0) {
                $totalProductsToProcess += $this->countRootProductModels($familyCodes);
                $familyCodes = [];
            }
        }
        $totalProductsToProcess += $this->countRootProductModels($familyCodes);

        $this->familyReader->rewind();

        return $totalProductsToProcess;
    }

    private function countRootProductModels(array $familyCodes): int
    {
        $cursor = $this->getRootProductModelsForFamily($familyCodes);

        return $cursor->count();
    }

    private function getRootProductModelsForFamily(array $familyCodes): CursorInterface
    {
        $pqb = $this->queryBuilderFactory->create();
        $pqb->addFilter('family', Operators::IN_LIST, $familyCodes);
        $pqb->addFilter('parent', Operators::IS_EMPTY, null);

        return $pqb->execute();
    }
}
