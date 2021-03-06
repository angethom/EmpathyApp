<?php

/* 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

function ea_email_sent_shortcode() {
    ob_start(); //++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++


    // Reading from superglobals
    $tCallerSkypeNameSg = esc_sql($_POST["skype_name"]);
    // ..verifying that the skype name exists
    if (verifyUserNameExists($tCallerSkypeNameSg) === false) {
        echo "<h3><i><b>Incorrect Skype name</b> - email not sent. Please go back and try again.</i></h3>";
        exit();
    }
    $tLengthNr = $_POST["length"];
    if (is_numeric($tLengthNr) === false) {
        handleError("Length variable was not numeric - possible SQL injection attempt");
    }

    // Setting up variables based on the superglobals
    $tCallerIdNr = getIdByUserName($tCallerSkypeNameSg);
    $tUniqueDbIdentifierSg = uniqid("id-", true); // http://php.net/manual/en/function.uniqid.php
    $tCallerDisplayNameSg = getDisplayNameById($tCallerIdNr);
    $tCallerEmailSg = getEmailById($tCallerIdNr);
    $tEmpathizerDisplayNameSg = getDisplayNameById(get_current_user_id());
    
    // If this is the first call: reduce the donation amount.
    $tAdjustedLengthNr = $tLengthNr;
    if(isFirstCall($tCallerIdNr) == true) {
        $tAdjustedLengthNr = $tAdjustedLengthNr - Constants::initial_call_minute_reduction;
    }
    $tRecDonationNr = (int)round(get_donation_multiplier() * $tAdjustedLengthNr);

    // Create the contents of the email message.
    $tMessageSg = "Hi " . $tCallerDisplayNameSg . ",

Thank you so much for your recent empathy call! Congratulations on contributing to a more empathic world. :)

You talked with: {$tEmpathizerDisplayNameSg}
Your Skype session duration was: {$tLengthNr} minutes
Your recommended contribution is: \${$tRecDonationNr}

Please follow this link to complete payment within 24 hours: " . getBaseUrl() . pages::donation_form . "?recamount={$tRecDonationNr}&dbToken={$tUniqueDbIdentifierSg}

See you next time!

The Empathy Team

PS
If you have any feedback please feel free to reply to this email and tell us your ideas or just your experience!
";

    // If the donation is greater than 0: send an email to the caller.
    if ($tRecDonationNr > 0) {
        ea_send_email($tCallerEmailSg, "Empathy App Payment", $tMessageSg);
        echo "<h3>Email successfully sent to caller.</h3>";
    } else {
        echo "<h4>No email sent: first time caller and call length was five minutes or less.</h4>";
    }

    // Add a new row to the db CallRecords table.
    db_insert(array(
        DatabaseAttributes::date_and_time        => current_time('mysql', 1),
        DatabaseAttributes::recommended_donation => $tRecDonationNr,
        DatabaseAttributes::call_length          => $tLengthNr,
        DatabaseAttributes::database_token       => $tUniqueDbIdentifierSg,
        DatabaseAttributes::caller_id            => $tCallerIdNr,
        DatabaseAttributes::empathizer_id        => get_current_user_id()
    ));
    
    
    $ob_content = ob_get_contents(); //+++++++++++++++++++++++++++++++++++++++++
    ob_end_clean();
    return $ob_content;
}

// Create shortcode for the email sent page.
// The 1st argument is the name of the shortcode, meaning that it will be used as "[<NAME>]" on a WP page.
// The 2nd argument is the name of the PHP function above, which will be used to insert text into the webpage.
add_shortcode('ea_email_sent', 'ea_email_sent_shortcode');
