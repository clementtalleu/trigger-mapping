## 🧙‍♂️ Create a New Trigger Interactively (`make:trigger`)

```bash
php bin/console make:trigger
```

This command, powered by the Symfony MakerBundle, is your go-to tool for creating a new trigger from scratch. It launches an interactive wizard that guides you through the entire process, step-by-step.

The command will ask you a series of questions to gather all the necessary information, such as:
* Which entity the trigger should be associated with.
* A unique name for the trigger.
* The database events it should react to (`INSERT`, `UPDATE`, `DELETE`).
* The timing (`BEFORE` or `AFTER`) and scope (`ROW` or `STATEMENT`).
* How you want to store the trigger logic (as a PHP class or raw `.sql` files).

#### What it Does

Once you have answered all the questions, the command performs three key actions:

1.  **Creates the Template Files:** It generates the necessary boilerplate files for your trigger logic (either a PHP class or `.sql` files) with `TODO` placeholders, ready for you to fill in.
2.  **Adds the Mapping:** It automatically modifies the chosen entity class to add the corresponding `#[Trigger]` attribute with all the correct parameters.
3.  **Generates a Migration:** If enabled in your configuration, it will also create a new Doctrine migration file to apply the new trigger to your database.

This is the fastest and most reliable way to create a new, fully configured trigger and ensure it is correctly mapped in your application.


