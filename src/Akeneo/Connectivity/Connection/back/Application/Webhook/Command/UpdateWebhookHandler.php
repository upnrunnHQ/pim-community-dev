<?php
declare(strict_types=1);

namespace Akeneo\Connectivity\Connection\Application\Webhook\Command;

use Akeneo\Connectivity\Connection\Domain\Settings\Exception\ConstraintViolationListException;
use Akeneo\Connectivity\Connection\Domain\Webhook\Model\Read\ConnectionWebhook as ReadConnectionWebhook;
use Akeneo\Connectivity\Connection\Domain\Webhook\Model\Write\ConnectionWebhook as WriteConnectionWebhook;
use Akeneo\Connectivity\Connection\Domain\Webhook\Persistence\Query\SelectWebhookSecretQuery;
use Akeneo\Connectivity\Connection\Domain\Webhook\Persistence\Repository\ConnectionWebhookRepository;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author    Willy Mesnage <willy.mesnage@akeneo.com>
 * @copyright 2020 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class UpdateWebhookHandler
{
    /** @var ConnectionWebhookRepository */
    private $repository;

    /** @var ValidatorInterface */
    private $validator;

    /** @var SelectWebhookSecretQuery */
    private $selectWehbookSecretQuery;

    /** @var GenerateWebhookSecretHandler */
    private $generateWebhookSecretHandler;

    public function __construct(
        ConnectionWebhookRepository $repository,
        ValidatorInterface $validator,
        SelectWebhookSecretQuery $selectWehbookSecretQuery,
        GenerateWebhookSecretHandler $generateWebhookSecretHandler
    ) {
        $this->repository = $repository;
        $this->validator = $validator;
        $this->selectWehbookSecretQuery = $selectWehbookSecretQuery;
        $this->generateWebhookSecretHandler = $generateWebhookSecretHandler;
    }

    public function handle(UpdateWebhookCommand $command): ReadConnectionWebhook
    {
        $connectionCode = $command->code();
        $webhook = new WriteConnectionWebhook($connectionCode, $command->enabled(), $command->url());
        $violations = $this->validator->validate($webhook);
        if (0 !== $violations->count()) {
            throw new ConstraintViolationListException($violations);
        }
        $this->repository->update($webhook);

        if (!$secret = $this->selectWehbookSecretQuery->execute($connectionCode)) {
            $secret = $this->generateWebhookSecretHandler->handle(new GenerateWebhookSecretCommand($connectionCode));
        }

        return new ReadConnectionWebhook($connectionCode, $command->enabled(), $secret, $command->url());
    }
}
