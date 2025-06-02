<?php declare(strict_types=1);
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2025 Dirk Wenzel <wenzel@cps-it.de>
 *  All rights reserved
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 * A copy is found in the text file GPL.txt and important notices to the license
 * from the author is found in LICENSE.txt distributed with these scripts.
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

namespace CPSIT\ImportExportCore;

use CPSIT\ImportExportCore\Messaging\Message;
use CPSIT\ImportExportCore\Messaging\MessageInterface;

/**
 * Class GenerateFileResource
 *
 * Generates a file object (\TYPO3\CMS\Core\Resource\File)
 * by reading a given file by name and optional source path.
 * The source path can be an external URL too.
 *
 * We check whether it already exist inside the storage (given by storageId).
 * If not this pre processor copies the file to the storage
 * and adds the file object to the file index repository.
 * If it exist we use the according file object.
 *
 * Finally the file object is added to the target field of
 * the record. Fields with single file references and object
 * storage of file references are handled.
 */
interface LoggingInterface
{
    public const ERROR_UNKNOWN_MESSAGE = 'Unknown message';
    public const ERROR_UNKNOWN_TITLE = 'Unknown title';
    public const NOTICE_UNKNOWN_MESSAGE = 'Unknown message';
    public const NOTICE_UNKNOWN_TITLE = 'Unknown title';
    public const DEFAULT_MESSAGE_TITLE = 'Unknown message title';
    public const DEFAULT_UNKNOWN_MESSAGE = 'Unknown message';
    /**
     * Returns error codes for current component.
     * Must be an array in the form
     * [
     *  <id> => ['errorTitle', 'errorDescription']
     * ]
     * 'errorDescription' may contain placeholder (%s) for arguments.
     * @return array
     */
    public function getErrorCodes(): array;

    /**
     * Returns notice codes for current component.
     * Override this method in instances with actual codes.
     * Must be an array in the form
     * [
     *  <id> => ['title', 'description']
     * ]
     * 'description' may contain placeholder (%s) for arguments.
     */
    public function getNoticeCodes(): array;

    /**
     * Creates an error message and adds it to the message container
     *
     * @param int $id Error id
     * @param array|null $arguments Optional arguments. Will be used as arguments for formatted message.
     * @param array|null $additionalInformation Optional array with additional information
     */
    public function logError($id, ?array $arguments = null, ?array $additionalInformation = null): void;

    /**
     * Creates a notice and adds it to the message container
     *
     * @param int $id Error id
     * @param array|null $arguments Optional arguments. Will be used as arguments for formatted message.
     * @param array|null $additionalInformation Optional array with additional information
     */
    public function logNotice($id, ?array $arguments = null, ?array $additionalInformation = null): void;

    /**
     * Logs a message
     *
     * @param string $title Message title
     * @param string $description Message content
     * @param int $severity Message severity
     * @param int|null $id Optional message ID
     * @param array|null $additionalInformation Optional additional information
     */
    public function logMessage($title, $description, $severity = Message::SEVERITY_OK, $id = null, ?array $additionalInformation = null): void;

    /**
     * Renders a title
     *
     * @param int $id Message ID
     * @param array $codes An array of codes.
     * @param string $default Default title
     * @return string
     */
    public function renderTitle($id, array $codes, $default = \CPSIT\ImportExportCore\LoggingInterface::DEFAULT_MESSAGE_TITLE): string;

    /**
     * Returns all messages.
     * Messages are kept.
     * @return array<MessageInterface>
     */
    public function getMessages(): array;

    /**
     * Returns and purges all messages from the message container
     */
    public function getAndPurgeMessages(): array;

    /**
     * Tells by id if a container has a certain message
     * Note: not all messages must have an id!
     */
    public function hasMessageWithId($id): bool;
}
