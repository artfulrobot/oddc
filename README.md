# oddc

## Metadata

- **Source**

   This is a free text string passed in on the query string as source=...
   It is stored on the Contribution's built-in source field.

- **Campaign**

   This is the CiviCRM campaign field. It is stored on both the Contribution and
   the recurring Contribution recordss.

- **Project**

   This is a string and is stored on a custom field on contributions.

## Email Dashboard

### Configuration.

Stored as a JSON blob with key `oddc_dashboards`. IT looks like this:

    {
        mailingLists: [123, 456, 789], // group IDs to show in dash.
    }

## Automation: tag people who unsubscribe from Direct Mail emails.

If the Mailing has a Campaign of type "Direct Mail" then people who unsubscribe
get auto tagged with "No fundraising emails"

Implemented with `hook_civicrm_unsubscribeGroups`

## Automation: CiviRules

There's a CiviRules Action defined called `OptOutPropagate`. You need to
set up a civirule on "Individual is changed" and conditions:

- Field Value Comparison: `Individual.is_opt_out = 1`
- OR Field Value Comparison: `Individual.do_not_email = 1`

It means when someone opts-out they are removed from all mailing lists
[that are flagged as needing sync with Klaviyo]
