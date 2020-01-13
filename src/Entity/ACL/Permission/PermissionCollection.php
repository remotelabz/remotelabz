<?php

namespace App\Entity\ACL\Permission;

use TheSeer\Tokenizer\TokenCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Common\Collections\Expr\ClosureExpressionVisitor;

/**
 * A collection of permissions.
 */
class PermissionCollection
{
    /**
     * An array containing the entries of this collection.
     * 
     * @var array
     */
    private $elements;
}