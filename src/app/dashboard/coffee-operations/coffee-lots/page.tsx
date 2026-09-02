import FinanceManagerOnly from "@/components/auth/finance-manager-only";
import CoffeeLotManagement from "@/components/coffee-lots/coffee-lot-management";

export default function CoffeeLotsPage() {
  return (
    <FinanceManagerOnly>
      <CoffeeLotManagement />
    </FinanceManagerOnly>
  );
}
