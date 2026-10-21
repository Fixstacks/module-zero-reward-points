# Zero_RewardPoints

Reward points: signed-in customers earn them and pay part of an order with
them.

- **Earning**: on paid orders (each paid invoice: its products after
  discounts × Points per 1 Spent; not shipping or tax), for signing up, for an
  approved review (once per product), or by hand (Customers > Reward Points).
- **Using**: at checkout, "You have N points. Use N for $X off" in the order
  summary (Zero Checkout, under the discount code). The points become part of
  the order's **discount** ("Reward points" in its description), spread over
  the products: tax, invoices, credit memos, emails and every "Discount" line
  count them with nothing else to change. Limits: Fewest Points to Use, Most
  of an Order Paid with Points (% of the products after other discounts).
  Taken from the balance when the order is placed (an order can't spend
  points twice: the ledger rows are locked); given back if placing fails.
- **Cancelled or refunded**: a cancelled order gives its points back; a
  credit memo gives back the refunded products' share of them and takes back
  that share of the points the order earned (no more than the balance).
- **Expiry**: earned points expire after Points Expire After, Days (cron
  `zero_reward_expire`, daily); spending uses the soonest to expire first.
  The account shows what expires within 30 days.
- **My Account > Reward Points**: balance and worth, how to earn, history.
- **Product pages**: "Earn N reward points" under the price.
- **Customers > Reward Points** (admin): who has how many, search by email
  (a customer's every change), add or take points with a note the customer
  sees.

Points are per website. Settings: Stores > Configuration > Zero > Reward
Points. Tables: `zero_reward_transaction` (the ledger); columns on `quote`
(points asked for and used, amount) and `sales_order` (used, given back,
earned, taken back).

## Install

Requires Magento Open Source 2.4.7–2.4.9 and PHP 8.2–8.4 with the GD extension built with WebP (`php -r 'var_dump(function_exists("imagewebp"));'` says `true`; Magento's own PHP requirements cover the rest). Without WebP in GD, Zero skips its WebP image copies and serves the originals.

```bash
composer require zerofront/module-zero-reward-points
bin/magento setup:upgrade
bin/magento setup:di:compile           # production mode
bin/magento setup:static-content:deploy # production mode
bin/magento cache:flush
```

Composer installs `zerofront/module-zero-checkout` with it.

Part of [`zerofront/zero`](https://zerofront.dev), which installs the Zero theme and every Zero module at once.

## Tests

`Test/Unit/Model/RedemptionTest.php`; `dev/tests/zero-e2e/tests/rewards.spec.ts`
(welcome points used at checkout, an order placed), `rewards-admin.spec.ts`.

## Disabling or removing

Its data lives in its own tables (`zero_reward_transaction`). With the module's code still in
place, `bin/magento module:disable Zero_RewardPoints` followed by `setup:upgrade`
**drops those tables** (Magento's declarative schema), and enabling the module
again brings them back empty. To keep the data, switch the feature off in
Stores > Configuration where it has a setting, or back up first:
`bin/magento setup:upgrade --safe-mode=1` dumps the data before anything is
dropped (`--data-restore=1` puts it back). Deleting the module's code instead
leaves the tables in place.

## License

OSL-3.0 or AFL-3.0, your choice: see `NOTICE.md`.

---

<a href="https://vilmate.com"><picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/zerofrontdev/zero/main/docs/images/vilmate-logo-dark.svg">
  <img src="https://raw.githubusercontent.com/zerofrontdev/zero/main/docs/images/vilmate-logo.svg" alt="Vilmate" height="34" align="right">
</picture></a>

Zero is made by [Fixstacks](https://fixstacks.com) in partnership with [Vilmate](https://vilmate.com), a software development company.
Documentation: https://zerofront.dev/docs. Demo store: https://demo.zerofront.dev.
