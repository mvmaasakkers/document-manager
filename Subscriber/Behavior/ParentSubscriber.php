<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Component\DocumentManager\Subscriber\Behavior;

use Sulu\Component\DocumentManager\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Sulu\Component\DocumentManager\Event\HydrateEvent;
use Sulu\Component\DocumentManager\MetadataFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Sulu\Component\DocumentManager\Behavior\ParentBehavior;
use PHPCR\NodeInterface;
use ProxyManager\Proxy\LazyLoadingInterface;
use Sulu\Component\DocumentManager\ProxyFactory;
use Sulu\Component\DocumentManager\DocumentInspector;
use Sulu\Component\DocumentManager\DocumentManager;
use Sulu\Component\DocumentManager\Event\PersistEvent;
use Sulu\Component\DocumentManager\Event\MoveEvent;

/**
 * Set the parent and children on the doucment
 */
class ParentSubscriber implements EventSubscriberInterface
{
    private $proxyFactory;
    private $inspector;
    private $documentManager;

    /**
     * @param ProxyFactory $proxyFactory
     */
    public function __construct(
        ProxyFactory $proxyFactory,
        DocumentInspector $inspector,
        DocumentManager $documentManager

    )
    {
        $this->proxyFactory = $proxyFactory;
        $this->inspector = $inspector;
        $this->documentManager = $documentManager;
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            Events::HYDRATE => 'handleHydrate',
            Events::PERSIST => 'handleChangeParent',
            Events::MOVE => 'handleMove',
        );
    }

    public function handleMove(MoveEvent $event)
    {
        $document = $event->getDocument();
        $node = $this->inspector->getNode($event->getDocument());
        $this->mapParent($document, $node);
    }

    /**
     * @param HydrateEvent $event
     */
    public function handleHydrate(HydrateEvent $event)
    {
        $document = $event->getDocument();
        $node = $event->getNode();

        if (!$document instanceof ParentBehavior) {
            return;
        }

        if ($node->getDepth() == 0) {
            throw new \RuntimeException(sprintf(
                'Cannot apply parent behavior to root node "%s" with type "%s" for document of class "%s"',
                $node->getPath(),
                $node->getPrimaryNodeType()->getName(),
                get_class($document)
            ));
        }

        $this->mapParent($document, $node);
    }

    /**
     * @param PersistEvent $event
     */
    public function handleChangeParent(PersistEvent $event)
    {
        $document = $event->getDocument();

        if (!$document instanceof ParentBehavior) {
            return;
        }

        $parentDocument = $document->getParent();

        if (!$parentDocument) {
            return;
        }

        $node = $this->inspector->getNode($document);
        $parentNode = $this->inspector->getNode($parentDocument);

        if ($parentNode->getPath() === $node->getParent()->getPath()) {
            return;
        }

        $this->documentManager->move($document, $parentNode->getPath());
    }

    private function mapParent($document, NodeInterface $node)
    {
        // TODO: performance warning: We are eagerly fetching the parent node
        $targetNode = $node->getParent();
        $document->setParent($this->proxyFactory->createProxyForNode($document, $targetNode));
    }
}
