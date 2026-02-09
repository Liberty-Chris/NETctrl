Project. NETctrl WordPress plugin.

Working agreements.
Work only inside the netctrl plugin folder unless updating README.md or this AGENTS.md.
Use custom database tables for sessions and entries.
Create tables on activation using dbDelta and store a netctrl_db_version option.
Expose functionality via WP REST API under /wp-json/netctrl/v1.
All endpoints must require authentication and current_user_can run_net.
Create a net_control role and run_net capability on activation.
Build a minimal Net Control Console admin page that can start a session add entries list entries and close session.
Provide a public shortcode [netctrl_log id="123"] to display a session log and offer a Download PDF link.
