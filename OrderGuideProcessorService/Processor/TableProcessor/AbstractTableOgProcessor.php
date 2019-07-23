<?php
declare(strict_types=1);
/**
 *  * Created by PhpStorm.
 * User: Logachev Sergey ( @LogachevSergey )
 * Date: 7/20/2018
 * Time: 1:38 PM
 */

namespace App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Processor\TableProcessor;


use App\Entity\Main\LocationVendor;
use App\Utils\EDI\Entity\EdiProcessorSetupInterface;
use App\Utils\EDI\OrderGuideImportService\Entity\OrderGuideError;
use App\Utils\EDI\OrderGuideImportService\Entity\OrderGuideFile;
use App\Utils\EDI\OrderGuideImportService\Entity\OrderGuideFileItem;
use App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Parser\OrderGuideParserInterface;
use App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Processor\AbstractOgProcessor;
use App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Serializer\Naming\MultipleNamingAnnotationStrategy;
use JMS\Serializer\Handler\HandlerRegistry;
use JMS\Serializer\Handler\SubscribingHandlerInterface;
use JMS\Serializer\Serializer as JmsSerializer;
use JMS\Serializer\SerializerBuilder;
use Symfony\Component\HttpFoundation\File\File;
use Webmozart\Assert\Assert;

/**
 * Implements basic structure validations.
 *
 * @package App\Utils\OrderGuideService\Processor\CustomProcessor
 */
abstract class AbstractTableOgProcessor extends AbstractOgProcessor implements TableOgProcessorInterface
{
    /** @var JmsSerializer */
    protected $serializer;

    /** @var OrderGuideFile */
    protected $ogFileObject;

    private $serializerHandlers = [];


    /**
     * @param array $normalizedItems
     * @param array $locationVendors
     *
     * @return OrderGuideFileItem[]
     */
    abstract protected function getItemsFromArray(array $normalizedItems, array $locationVendors): array;

    /**
     * @param array $normalizedItems
     * @param array $locationVendors
     * @param array $errors
     *
     * @return array
     * @throws \Exception
     */
    protected function getOgFilesFromArray(array $normalizedItems, array $locationVendors, array $errors): array
    {
        Assert::notEmpty($locationVendors, 'You didn\'t pass location vendor.');
        Assert::allIsInstanceOf($locationVendors, LocationVendor::class, 'At least one of location vendors is wrong.');

        $this->ogFileObject = new OrderGuideFile($this, $locationVendors);
        $this->ogFileObject->setDate(new \DateTimeImmutable());
        $this->ogFileObject->setErrors($errors);
        $this->ogFileObject->setItems($this->getItemsFromArray($normalizedItems, $locationVendors));

        return [$this->ogFileObject];
    }

    /**
     * @inheritdoc
     */
    public function isFileProcessable(File $invoiceFile, array $locationVendors): bool
    {
        foreach ($this->parsers as $parser) {
            if (empty($fileStructure = $this->getFileHeader($invoiceFile, $parser))) {
                continue;
            }

            if ($fileStructure === $this->fileStructure) {
                $this->parser = $parser;

                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \ReflectionException
     */
    final protected function contentNormalization(array $rawContentArray): array
    {
        // Header less files have no fixed structure
        if ($this->fileIsHeaderless) {
            return [$rawContentArray, []];
        }

        $ethalonAmountOfFields = \count($this->getFileStructure());
        $ogItems = $errors = [];

        foreach ($rawContentArray as $rawContentItem) {
            // Count of columns validation
            if (\count($rawContentItem) !== $ethalonAmountOfFields) {
                $errorMessage = 'Item contains wrong amount of fields. Fix it and upload again.';
                $errors[] = $this->deserializeError($rawContentItem, OrderGuideError::WRONG_STRING_FORMAT, $errorMessage);

                continue;
            }

            $ogItems[] = $rawContentItem;
        }

        return $this->AdvancedContentNormalization($ogItems, $errors);
    }

    /**
     * Method can be used for an additional normalization.
     *
     * @param array $ogItems
     * @param array $errors
     *
     * @return array
     */
    protected function AdvancedContentNormalization(array $ogItems, array $errors): array
    {
        return [$ogItems, $errors];
    }

    /**
     * Some common actions, specific for particular type of processors, launches right before normalization.
     * E.g. TableProcessors need serializer setup.
     */
    final protected function preContentNormalization(): void
    {
        $this->serializer = $this->setupJmsSerializer();
    }

    /**
     * @inheritdoc
     */
    final public function addSerializerHandler(SubscribingHandlerInterface $handler): void
    {
        if (!in_array($handler, $this->serializerHandlers, true)) {
            $this->serializerHandlers[] = $handler;
        }
    }

    /**
     * All necessary stuff to make serializer work with data properly.
     *
     * @return JmsSerializer
     */
    private function setupJmsSerializer(): JmsSerializer
    {
        $serializerBuilder = new SerializerBuilder();

        // Register handlers for fields normalization
        foreach ($this->serializerHandlers as $handler) {
            $serializerBuilder->configureHandlers(function (HandlerRegistry $registry) use ($handler) {
                $registry->registerSubscribingHandler($handler);
            }
            );
        }

        $namingStrategy = new MultipleNamingAnnotationStrategy();
        /** @var OrderGuideParserInterface $parser */
        $parser = $this->parser;
        $namingStrategy->setNamingKey($parser::getAnnotationKey());
        $serializerBuilder->setPropertyNamingStrategy($namingStrategy);

        return $serializerBuilder->build();
    }

    /**
     * Deserialize ogItem instance from array.
     *
     * @param array $ogItemArray
     *
     * @return OrderGuideFileItem
     */
    final protected function deserializeOgItemFromArray(array $ogItemArray): OrderGuideFileItem
    {
        return $this->serializer->fromArray($ogItemArray, OrderGuideFileItem::class);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \ReflectionException
     */
    final protected function deserializeError(array $wrongItem, string $errorLevel, string $errorMessage = '', LocationVendor $locationVendor = null): OrderGuideError
    {
        /** @var OrderGuideError $error */
        $error = $this->serializer->fromArray($wrongItem, OrderGuideError::class);

        $error->setLocationVendor($locationVendor);
        $error->setErrorLevel($errorLevel);
        $error->setMessage($errorMessage);

        return $error;
    }

    /**
     * {@inheritDoc}
     */
    public function supports(?EdiProcessorSetupInterface $processorSetup): bool
    {
        // Table type of processor doesn't use setup.
        return true;
    }
}