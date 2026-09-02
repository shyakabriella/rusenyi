import { redirect } from "next/navigation";

export default function CoffeeLotsRedirect() {
  redirect(
    "/dashboard/coffee-operations/coffee-lots",
  );
}
