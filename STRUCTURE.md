# Project structure

Every file in the skeleton, generated from the folders. Each placeholder file says what goes in it.

```
minivora/
├── .github/
│   ├── workflows/
│   │   ├── ci.yml
│   │   └── deploy.yml
│   ├── CODEOWNERS
│   └── PULL_REQUEST_TEMPLATE.md
├── app/
│   ├── Ai/
│   │   ├── Agents/
│   │   │   ├── Ceo/
│   │   │   │   └── CeoAgent.php
│   │   │   ├── Cfo/
│   │   │   │   └── CfoAgent.php
│   │   │   ├── Chro/
│   │   │   │   └── ChroAgent.php
│   │   │   ├── Cmo/
│   │   │   │   └── CmoAgent.php
│   │   │   ├── Coo/
│   │   │   │   └── CooAgent.php
│   │   │   ├── Cro/
│   │   │   │   └── CroAgent.php
│   │   │   ├── Cto/
│   │   │   │   └── CtoAgent.php
│   │   │   ├── Cxo/
│   │   │   │   └── CxoAgent.php
│   │   │   └── Risk/
│   │   │       └── RiskAgent.php
│   │   ├── Approvals/
│   │   │   └── ApprovalService.php
│   │   ├── Coordinator/
│   │   │   ├── BusinessRules.php
│   │   │   └── Coordinator.php
│   │   ├── Eval/
│   │   │   ├── EvalRunner.php
│   │   │   └── questions.json
│   │   ├── Guards/
│   │   │   └── NumberGuard.php
│   │   ├── Http/
│   │   │   └── Controllers/
│   │   │       ├── ApprovalController.php
│   │   │       └── ChatController.php
│   │   ├── Jobs/
│   │   │   └── RunProactiveChecks.php
│   │   ├── Llm/
│   │   │   ├── GeminiProvider.php
│   │   │   ├── KeyRotator.php
│   │   │   ├── LlmClient.php
│   │   │   ├── OpenAiCompatibleProvider.php
│   │   │   └── PiiMasker.php
│   │   ├── Models/
│   │   │   ├── Approval.php
│   │   │   └── Insight.php
│   │   ├── Proactive/
│   │   │   ├── DailyChecks.php
│   │   │   ├── FeedRanker.php
│   │   │   ├── HourlyChecks.php
│   │   │   ├── MorningBrief.php
│   │   │   └── WeeklyChecks.php
│   │   ├── Registry/
│   │   │   ├── AiFunction.php
│   │   │   └── FunctionRegistry.php
│   │   ├── Understanding/
│   │   │   └── QuestionRouter.php
│   │   └── README.md
│   ├── Core/
│   │   ├── AuditLog/
│   │   │   ├── ActivityLog.php
│   │   │   └── ActivityLogger.php
│   │   ├── Money/
│   │   │   ├── MoneyFormatter.php
│   │   │   └── Piasters.php
│   │   ├── Permissions/
│   │   │   ├── PermissionMatrix.php
│   │   │   └── Role.php
│   │   └── Tenancy/
│   │       ├── BelongsToStore.php
│   │       ├── CurrentStore.php
│   │       ├── ReservedSubdomains.php
│   │       ├── ResolveStoreFromHost.php
│   │       ├── ResolveStoreFromSession.php
│   │       └── StoreScope.php
│   ├── Importers/
│   │   └── Adorna/
│   │       ├── Jobs/
│   │       │   └── RunNightlyAdornaImport.php
│   │       ├── Mappers/
│   │       │   ├── CustomerMapper.php
│   │       │   ├── OrderMapper.php
│   │       │   ├── ProductMapper.php
│   │       │   └── VisitMapper.php
│   │       ├── AdornaImporter.php
│   │       └── README.md
│   └── Modules/
│       ├── Accounts/
│       │   ├── Data/
│       │   │   └── InviteMemberData.php
│       │   ├── Events/
│       │   │   └── MemberInvited.php
│       │   ├── Http/
│       │   │   └── Controllers/
│       │   │       ├── MemberController.php
│       │   │       └── TwoFactorController.php
│       │   ├── Models/
│       │   │   ├── Invitation.php
│       │   │   └── StoreMember.php
│       │   ├── Policies/
│       │   │   └── MemberPolicy.php
│       │   ├── Services/
│       │   │   └── MembershipService.php
│       │   └── README.md
│       ├── Analytics/
│       │   ├── AiFunctions/
│       │   │   └── AnalyticsFunctions.php
│       │   ├── Http/
│       │   │   └── Controllers/
│       │   │       ├── AnalyticsController.php
│       │   │       └── TrackController.php
│       │   ├── Jobs/
│       │   │   └── PruneOldPageViews.php
│       │   ├── Models/
│       │   │   ├── PageView.php
│       │   │   └── VisitSession.php
│       │   ├── Services/
│       │   │   ├── DemandService.php
│       │   │   ├── KpiService.php
│       │   │   └── TrackingService.php
│       │   └── README.md
│       ├── Builder/
│       │   ├── AiFunctions/
│       │   │   └── BuilderFunctions.php
│       │   ├── Data/
│       │   │   ├── SectionData.php
│       │   │   └── ThemeData.php
│       │   ├── Events/
│       │   │   └── PagePublished.php
│       │   ├── Http/
│       │   │   └── Controllers/
│       │   │       ├── MediaController.php
│       │   │       ├── PageController.php
│       │   │       └── ThemeController.php
│       │   ├── Models/
│       │   │   ├── MediaFile.php
│       │   │   ├── Page.php
│       │   │   ├── PageVersion.php
│       │   │   ├── Section.php
│       │   │   └── Theme.php
│       │   ├── Services/
│       │   │   ├── MediaService.php
│       │   │   ├── PageBuilderService.php
│       │   │   ├── SectionRegistry.php
│       │   │   └── ThemeService.php
│       │   └── README.md
│       ├── Cart/
│       │   ├── Data/
│       │   │   └── AddToCartData.php
│       │   ├── Http/
│       │   │   └── Controllers/
│       │   │       └── CartController.php
│       │   ├── Models/
│       │   │   ├── Cart.php
│       │   │   ├── CartEvent.php
│       │   │   └── CartItem.php
│       │   ├── Services/
│       │   │   └── CartService.php
│       │   └── README.md
│       ├── Catalog/
│       │   ├── AiFunctions/
│       │   │   └── CatalogFunctions.php
│       │   ├── Data/
│       │   │   ├── ProductData.php
│       │   │   └── VariantData.php
│       │   ├── Events/
│       │   │   ├── SlugChanged.php
│       │   │   └── StockBelowReorder.php
│       │   ├── Http/
│       │   │   └── Controllers/
│       │   │       ├── CategoryController.php
│       │   │       ├── ProductController.php
│       │   │       └── ReviewController.php
│       │   ├── Listeners/
│       │   │   └── RestockOnCancel.php
│       │   ├── Models/
│       │   │   ├── Category.php
│       │   │   ├── Product.php
│       │   │   ├── ProductImage.php
│       │   │   ├── ProductVariant.php
│       │   │   ├── Review.php
│       │   │   └── Wishlist.php
│       │   ├── Policies/
│       │   │   └── ProductPolicy.php
│       │   ├── Services/
│       │   │   ├── ImageService.php
│       │   │   ├── InventoryService.php
│       │   │   ├── ProductService.php
│       │   │   └── SearchService.php
│       │   └── README.md
│       ├── Checkout/
│       │   ├── AiFunctions/
│       │   │   └── CheckoutFunctions.php
│       │   ├── Data/
│       │   │   └── CheckoutData.php
│       │   ├── Events/
│       │   │   ├── OrderPaid.php
│       │   │   └── PaymentFailed.php
│       │   ├── Gateways/
│       │   │   ├── CodGateway.php
│       │   │   ├── FawryGateway.php
│       │   │   ├── InstaPayGateway.php
│       │   │   ├── KashierGateway.php
│       │   │   ├── PaymentGateway.php
│       │   │   └── PaymobGateway.php
│       │   ├── Http/
│       │   │   └── Controllers/
│       │   │       ├── CheckoutController.php
│       │   │       └── WebhookController.php
│       │   ├── Models/
│       │   │   ├── DiscountCode.php
│       │   │   ├── Lead.php
│       │   │   └── Payment.php
│       │   ├── Services/
│       │   │   ├── CheckoutService.php
│       │   │   ├── DiscountService.php
│       │   │   └── RiskService.php
│       │   └── README.md
│       ├── Fpa/
│       │   ├── AiFunctions/
│       │   │   └── FpaFunctions.php
│       │   ├── Http/
│       │   │   └── Controllers/
│       │   │       ├── BudgetController.php
│       │   │       └── ScenarioController.php
│       │   ├── Jobs/
│       │   │   └── GenerateMonthlyMemo.php
│       │   ├── Models/
│       │   │   ├── Budget.php
│       │   │   ├── BudgetLine.php
│       │   │   └── Scenario.php
│       │   ├── Services/
│       │   │   ├── ForecastService.php
│       │   │   ├── VarianceService.php
│       │   │   └── WhatIfService.php
│       │   └── README.md
│       ├── Ledger/
│       │   ├── AiFunctions/
│       │   │   └── LedgerFunctions.php
│       │   ├── Http/
│       │   │   └── Controllers/
│       │   │       ├── LedgerController.php
│       │   │       └── ReportController.php
│       │   ├── Jobs/
│       │   │   └── ReconcileGatewayPayouts.php
│       │   ├── Listeners/
│       │   │   ├── PostAdSpend.php
│       │   │   ├── PostCogsOnOrderShipped.php
│       │   │   ├── PostRefund.php
│       │   │   ├── PostSaleOnOrderPaid.php
│       │   │   └── ReverseOnReturn.php
│       │   ├── Models/
│       │   │   ├── Account.php
│       │   │   ├── JournalEntry.php
│       │   │   └── JournalLine.php
│       │   ├── Policies/
│       │   │   └── LedgerPolicy.php
│       │   ├── Services/
│       │   │   ├── LedgerService.php
│       │   │   ├── PostingRules.php
│       │   │   └── StatementService.php
│       │   └── README.md
│       ├── Marketing/
│       │   ├── AiFunctions/
│       │   │   └── MarketingFunctions.php
│       │   ├── Events/
│       │   │   └── AdSpendRecorded.php
│       │   ├── Http/
│       │   │   └── Controllers/
│       │   │       ├── AdSpendController.php
│       │   │       ├── CampaignController.php
│       │   │       ├── PixelController.php
│       │   │       └── SubscriberController.php
│       │   ├── Jobs/
│       │   │   ├── ImportMetaCampaigns.php
│       │   │   └── SendAbandonedCartReminders.php
│       │   ├── Models/
│       │   │   ├── AbandonedCheckout.php
│       │   │   ├── AdSpend.php
│       │   │   ├── Campaign.php
│       │   │   ├── CampaignMetric.php
│       │   │   ├── Pixel.php
│       │   │   └── Subscriber.php
│       │   ├── Services/
│       │   │   ├── AbandonedCartService.php
│       │   │   ├── CampaignVerdictService.php
│       │   │   ├── ConversionsApiService.php
│       │   │   ├── MetaImportService.php
│       │   │   ├── NewsletterService.php
│       │   │   └── PixelService.php
│       │   └── README.md
│       ├── Notifications/
│       │   ├── Http/
│       │   │   └── Controllers/
│       │   │       └── AlertController.php
│       │   ├── Listeners/
│       │   │   ├── SendOrderConfirmation.php
│       │   │   └── SendShippingUpdate.php
│       │   ├── Mail/
│       │   │   ├── AbandonedCartMail.php
│       │   │   ├── MorningBriefMail.php
│       │   │   ├── OrderConfirmationMail.php
│       │   │   └── OrderShippedMail.php
│       │   ├── Models/
│       │   │   └── Alert.php
│       │   ├── Services/
│       │   │   └── NotificationService.php
│       │   └── README.md
│       ├── Orders/
│       │   ├── AiFunctions/
│       │   │   └── OrderFunctions.php
│       │   ├── Data/
│       │   │   ├── ReturnRequestData.php
│       │   │   └── UpdateOrderStatusData.php
│       │   ├── Events/
│       │   │   ├── OrderCancelled.php
│       │   │   ├── OrderReturned.php
│       │   │   ├── OrderShipped.php
│       │   │   └── RefundIssued.php
│       │   ├── Http/
│       │   │   └── Controllers/
│       │   │       ├── OrderController.php
│       │   │       ├── ReturnController.php
│       │   │       ├── ShippingZoneController.php
│       │   │       └── TrackOrderController.php
│       │   ├── Models/
│       │   │   ├── Order.php
│       │   │   ├── OrderItem.php
│       │   │   ├── OrderStatusHistory.php
│       │   │   ├── Refund.php
│       │   │   ├── ReturnRequest.php
│       │   │   └── ShippingZone.php
│       │   ├── Policies/
│       │   │   └── OrderPolicy.php
│       │   ├── Services/
│       │   │   ├── InvoiceService.php
│       │   │   ├── OrderService.php
│       │   │   ├── ReturnService.php
│       │   │   └── ShippingService.php
│       │   └── README.md
│       ├── Platform/
│       │   ├── Data/
│       │   │   └── CreateStoreData.php
│       │   ├── Events/
│       │   │   └── StoreCreated.php
│       │   ├── Http/
│       │   │   └── Controllers/
│       │   │       ├── PlatformAdminController.php
│       │   │       └── StoreSignupController.php
│       │   ├── Models/
│       │   │   ├── Store.php
│       │   │   └── StoreDomain.php
│       │   ├── Policies/
│       │   │   └── StorePolicy.php
│       │   ├── Services/
│       │   │   ├── StoreCreationService.php
│       │   │   └── SubdomainService.php
│       │   └── README.md
│       └── Seo/
│           ├── Http/
│           │   └── Controllers/
│           │       ├── FeedController.php
│           │       ├── LlmsTxtController.php
│           │       ├── RobotsController.php
│           │       └── SitemapController.php
│           ├── Jobs/
│           │   └── RunSeoAudit.php
│           ├── Listeners/
│           │   ├── CreateRedirectOnSlugChange.php
│           │   └── RefreshSitemapOnPublish.php
│           ├── Models/
│           │   ├── Redirect.php
│           │   └── SeoSetting.php
│           ├── Services/
│           │   ├── FeedService.php
│           │   ├── IndexNowService.php
│           │   ├── JsonLdService.php
│           │   ├── SeoAuditService.php
│           │   └── SitemapService.php
│           └── README.md
├── config/
│   └── minivora.php
├── database/
│   ├── migrations/
│   │   └── PLAN.md
│   └── seeders/
│       ├── ChartOfAccountsSeeder.php
│       └── DemoStoresSeeder.php
├── design/
│   ├── tokens/
│   │   └── tokens.json
│   ├── build-tokens.mjs
│   └── README.md
├── docs/
│   ├── adr/
│   │   ├── 0001-laravel-mysql-on-hostinger.md
│   │   ├── 0002-multi-store-single-database.md
│   │   └── TEMPLATE.md
│   ├── README.md
│   └── technical-overview.md
├── e2e/
│   ├── owner-create-store.spec.ts
│   ├── playwright.config.ts
│   └── shopper-checkout.spec.ts
├── lang/
│   ├── ar/
│   │   ├── dashboard.php
│   │   ├── emails.php
│   │   ├── storefront.php
│   │   └── validation.php
│   ├── en/
│   │   ├── dashboard.php
│   │   ├── emails.php
│   │   ├── storefront.php
│   │   └── validation.php
│   ├── ar.json
│   └── en.json
├── resources/
│   ├── css/
│   │   └── tokens.css
│   ├── js/
│   │   ├── components/
│   │   │   ├── ui/
│   │   │   │   ├── badge.tsx
│   │   │   │   ├── button.tsx
│   │   │   │   ├── card.tsx
│   │   │   │   ├── glass-panel.tsx
│   │   │   │   └── input.tsx
│   │   │   ├── insight-card.tsx
│   │   │   ├── kpi-card.tsx
│   │   │   └── money.tsx
│   │   ├── layouts/
│   │   │   ├── dashboard-layout.tsx
│   │   │   ├── sidebar.tsx
│   │   │   └── topbar.tsx
│   │   ├── lib/
│   │   │   ├── i18n.ts
│   │   │   └── money.ts
│   │   ├── pages/
│   │   │   ├── ai-chat/
│   │   │   │   └── index.tsx
│   │   │   ├── analytics/
│   │   │   │   └── index.tsx
│   │   │   ├── approvals/
│   │   │   │   └── index.tsx
│   │   │   ├── builder/
│   │   │   │   ├── editor.tsx
│   │   │   │   ├── media.tsx
│   │   │   │   └── theme.tsx
│   │   │   ├── categories/
│   │   │   │   └── index.tsx
│   │   │   ├── customers/
│   │   │   │   └── index.tsx
│   │   │   ├── finance/
│   │   │   │   ├── budgets.tsx
│   │   │   │   ├── index.tsx
│   │   │   │   └── reports.tsx
│   │   │   ├── marketing/
│   │   │   │   ├── campaigns.tsx
│   │   │   │   └── index.tsx
│   │   │   ├── onboarding/
│   │   │   │   └── create-store.tsx
│   │   │   ├── orders/
│   │   │   │   ├── index.tsx
│   │   │   │   └── show.tsx
│   │   │   ├── platform-admin/
│   │   │   │   └── index.tsx
│   │   │   ├── products/
│   │   │   │   ├── edit.tsx
│   │   │   │   └── index.tsx
│   │   │   ├── seo/
│   │   │   │   └── index.tsx
│   │   │   ├── settings/
│   │   │   │   ├── domain.tsx
│   │   │   │   ├── index.tsx
│   │   │   │   ├── members.tsx
│   │   │   │   └── payments.tsx
│   │   │   └── today/
│   │   │       └── index.tsx
│   │   └── types/
│   │       └── generated.ts
│   └── views/
│       ├── emails/
│       │   ├── abandoned-cart.blade.php
│       │   ├── morning-brief.blade.php
│       │   ├── order-confirmation.blade.php
│       │   └── order-shipped.blade.php
│       └── storefront/
│           ├── layouts/
│           │   └── app.blade.php
│           ├── pages/
│           │   ├── account.blade.php
│           │   ├── blog-index.blade.php
│           │   ├── blog-post.blade.php
│           │   ├── cart.blade.php
│           │   ├── category.blade.php
│           │   ├── checkout.blade.php
│           │   ├── home.blade.php
│           │   ├── order-confirmation.blade.php
│           │   ├── page.blade.php
│           │   ├── product.blade.php
│           │   ├── search.blade.php
│           │   ├── shop.blade.php
│           │   └── track-order.blade.php
│           ├── partials/
│           │   ├── footer.blade.php
│           │   ├── header.blade.php
│           │   ├── product-card.blade.php
│           │   └── seo-head.blade.php
│           └── sections/
│               ├── brand-story.blade.php
│               ├── category-grid.blade.php
│               ├── featured-products.blade.php
│               ├── hero.blade.php
│               ├── image-banner.blade.php
│               ├── image-carousel.blade.php
│               ├── logo-carousel.blade.php
│               ├── social-carousel.blade.php
│               ├── testimonial-carousel.blade.php
│               ├── testimonials.blade.php
│               ├── text-block.blade.php
│               ├── video-gallery.blade.php
│               ├── video-hero.blade.php
│               ├── video-reels.blade.php
│               └── video.blade.php
├── routes/
│   ├── api.php
│   ├── console.php
│   ├── dashboard.php
│   └── storefront.php
├── tests/
│   ├── Feature/
│   │   ├── Checkout/
│   │   │   └── CheckoutTest.php
│   │   ├── Orders/
│   │   │   └── OrderStatusTest.php
│   │   └── Platform/
│   │       └── CreateStoreTest.php
│   ├── Isolation/
│   │   └── StoreIsolationTest.php
│   └── Unit/
│       ├── Ai/
│       │   ├── BusinessRulesTest.php
│       │   └── NumberGuardTest.php
│       ├── Core/
│       │   └── PiastersTest.php
│       └── Ledger/
│           ├── BalancedEntryTest.php
│           └── PostingRulesTest.php
├── .env.example
└── README.md
```
