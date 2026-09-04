# ARK Core ↔ Website boundary

Core is the shop operating system: repair orders, customers, communications, portal, intake, leads, installer, reporting, and shop identity.

Marketing website, SEO, Growth marketing tools, and website admin are **not** Core. They were extracted from this repository.

## What stays in Core

- Shop identity (`ShopSettings`: name, address, phone, hours, logo)
- Google review destination (`shop_settings.google_reviews_url`) for post-repair review requests — Core messaging, not Website SEO
- Customer portal and tokenized estimate / inspection / pay links
- Leads as operational authority (advisor intake, `LeadSource::Website` for historical/source labeling)
- Website-lead interrupt presenters for uncontacted leads already in Core
- `PUBLIC_DOMAIN` / `surfaces.public` as an optional **host routing** seam for portal co-location — not a marketing CMS
- Legacy `public_surface_settings` / Growth schema columns may remain for install compatibility; they are not product authority

## What left Core

- Public marketing pages (home, book, common problems, financing, RepairPal, SEO assets)
- Growth opportunity / attribution / journey explorer product surfaces
- Website admin / performance settings UI

## Future ARK Website

A future ARK Website product may **consume** Core shop facts (identity, hours, phone) for publication. It does not become shop authority.

ARK Website is conceptually independent of Connect: a shop may publish a site and receive email notifications without Connect; Connect enables direct Core workflow when that product exists. This document records the boundary only — no Website or Connect implementation claim.
