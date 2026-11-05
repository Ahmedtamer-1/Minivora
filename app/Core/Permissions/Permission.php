<?php

namespace App\Core\Permissions;

/**
 * Capabilities from the SRS §7.2 table. Used by policies, the dashboard and the AI function registry.
 */
enum Permission: string
{
    case DesignManage = 'design.manage';          // Store design, pages and publish
    case ProductsView = 'products.view';
    case ProductsManage = 'products.manage';      // Products and inventory
    case OrdersView = 'orders.view';
    case OrdersManage = 'orders.manage';          // Orders and fulfillment
    case RefundsView = 'refunds.view';
    case RefundsRequest = 'refunds.request';
    case RefundsManage = 'refunds.manage';        // Refunds and returns
    case MarketingManage = 'marketing.manage';    // Discounts and marketing
    case LedgerView = 'ledger.view';
    case LedgerManage = 'ledger.manage';          // Ledger, reports and exports
    case BudgetsView = 'budgets.view';
    case BudgetsManage = 'budgets.manage';        // Budgets and FP&A
    case AiAskFinance = 'ai.ask_finance';         // Ask the AI team finance questions
    case AiApprove = 'ai.approve';                // Approve AI actions (manager: up to a limit)
    case MembersManage = 'members.manage';        // Staff and roles
    case SettingsManage = 'settings.manage';      // Store settings, payments and domain
    case StoreDelete = 'store.delete';            // Delete or transfer the store
}
