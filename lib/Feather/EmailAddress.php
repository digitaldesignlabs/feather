<?php

/**
 * Feather Framework
 * @package DDL\Feather
 */

namespace DDL\Feather;

/**
 * EmailAddress
 * For working with email addresses. Honestly.
 * @copyright GG.COM Ltd
 * @license MIT
 * @author Mike Hall
 */
class EmailAddress
{
    /**
     * Validate an email address
     * @param string $email
     * @return boolean
     */
    public static function isValid($email)
    {
        // Check for vaid format
        $email = filter_var($email, FILTER_VALIDATE_EMAIL);
        if (empty($email)) {
            return NO;
        }

        // Check for MX and hope for the best.
        // There is a chance that a domain may not have MX yet still have
        // email (I think DNS falls back to A records where there is no MX?)
        // But I think it's rare enough not to worry about it.
        list ($local, $domain) = explode("@", $email);
        return !!dns_get_mx($domain, $hosts);
    }
}
