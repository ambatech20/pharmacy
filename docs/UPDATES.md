# Updating the live site

A short, plain-language guide to getting a change onto
`https://pharmacy.example.com`.

There is no SSH on the example hosting panel, so an update is a file upload.
If your host gives you SSH, use your normal deployment command instead.

---

## The normal loop

**1. You ask for the change.**
Describe it in your own words. Screenshots help.

**2. I make the change and test it.**
On my own machine, against a copy of the database. Nothing is touched on the
live site at this point. I run the full automated test suite before anything
goes up.

**3. I build one ZIP file.**
It appears in your **Downloads** folder, named something like
`pmas-update-20261019-1430.zip`. It contains only the files that changed.

**4. You upload it.** In your host's file manager:

   1. Open **File Manager**.
   2. Make sure the address bar shows `pharmacy.example.com` â€” the folder
      that contains `app`, `public` and `routes`. If you can see a folder
      called `public_html`, you are one level too deep.
   3. Click **Upload**, choose the ZIP file, and wait for it to finish.
   4. Find the ZIP in the file list, click the three dots next to it, and
      choose **Extract Files**. Confirm.
   5. Wait for the message that it extracted. **Do not close the window
      while it is still working.**
   6. Delete the ZIP from the server (optional, it does no harm).

**5. If I give you a maintenance link, open it once.**
Sometimes the ZIP contains a file called `_maintenance.php`. When it does, I
will give you an address that looks like:

    https://pharmacy.example.com/_maintenance.php?token=a1b2c3...

Open it in a browser, once. It will say what it is doing, then it deletes
itself. You do not need to keep the page or the link.

If I did not give you a link, there is nothing to open.

**6. Tell me it is done.**
I check the live site from outside and confirm the new version is running.

That is it. Nothing to type, nothing to install.

---

## What is never in the ZIP

Files in the ZIP go onto a public web server, so some things are deliberately
kept out:

- the automated tests, and this documentation
- the deployment tooling itself
- your `.env` file, which holds the database password
- anything under `storage/`, which is live business data

These live in the project's git repository only. A ZIP built for deployment
leaves them behind and prints what it skipped.

---

## If something looks wrong

**Nothing looks different.** The upload probably did not extract. Do step 4
again, and check you extracted into the folder that contains `app` rather
than into a subfolder.

**A page shows a white screen or an error.** Tell me immediately and do not
enter more data. The previous version is one ZIP away, so this is fixable.

**Wrong data in the system.** Do not "correct" it by editing records. Tell me
what you see and I will look at what wrote it.

**You need to go back to an earlier copy of the data.** See the **Backups**
page in the system; the restore steps are at the bottom of that page. This
puts the database back to the moment of the backup, so anything entered since
then is lost â€” ask me first if you are unsure.

---

## Keeping safe copies of the data

The **Backups** screen (System â†’ Backups, in the sidebar) downloads a single
file containing everything: medicines, products, purchases, sales, customers,
suppliers, accounts and settings.

- Take one **once a week**, or at the very least once a month.
- Keep it somewhere other than this computer: a USB stick in the pharmacy, or
  emailed to yourself.
- The system keeps **the last five** on the server as well, so a download that
  never arrived can be fetched again from the Backups page. Those are a safety
  net, not a substitute â€” they sit on the same machine as the website.
- A reminder appears on the dashboard, and a red mark on the Backups link,
  once it has been more than two weeks.

The backup file contains staff accounts, so treat it like a password.

---

## For whoever maintains this next

The technical side â€” how the ZIP is built, why the entry names use forward
slashes, the `deployed` git tag, and the maintenance page â€” belongs in your
deployment tooling's README. Keep that README with the tooling, not in the
public web root.

