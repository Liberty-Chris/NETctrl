NETctrl

WordPress plugin for amateur radio net control logging.
This repository contains the plugin under `/netctrl`.

## Operator access setup

1. Activate the NETctrl plugin in WordPress. Activation creates the custom `net_control` role and ensures it has the `run_net` capability for frontend operators.
2. In **Users > Add New** (or by editing an existing account), assign the user the **Net Control** role. This role is intended for standard operator accounts that only need NETctrl access.
3. Create a normal WordPress page such as `Net Control Console` and place the shortcode `[netctrl_console]` in the page content.
4. Share that page URL with operators. Logged-in users with the `run_net` capability will see the live console on the frontend without needing `wp-admin`. Visitors who are not logged in, or who do not have the required capability, will see an access message instead of the console.

## Console shortcodes

- `[netctrl_console]` renders the secure frontend operator console for logged-in users with the `run_net` capability.
- `[netctrl_log id="123"]` continues to render a session log view for a specific session and can still be used for public log pages.

## Testing the frontend console

1. Log in as an administrator and confirm the existing **Net Control** admin page still works in `wp-admin`.
2. Create or assign a user with the **Net Control** role.
3. Open the page containing `[netctrl_console]` while logged out and verify the login-required message appears.
4. Open the same page with a logged-in account that does not have `run_net` and verify the access denied message appears.
5. Open the page with a logged-in **Net Control** user and verify all console actions work from the frontend page:
   - start a session
   - add entries
   - view the entries list
   - close the session
   - view the recent sessions list
6. Optionally confirm `[netctrl_log id="123"]` still renders the public session log for a closed session page.
