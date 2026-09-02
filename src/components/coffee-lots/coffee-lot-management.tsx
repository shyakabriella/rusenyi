"use client";

import {
  Boxes,
  ChevronLeft,
  ChevronRight,
  Eye,
  LoaderCircle,
  Pencil,
  Plus,
  RotateCcw,
  Search,
  Warehouse,
  X,
} from "lucide-react";

import {
  type ReactNode,
  useCallback,
  useEffect,
  useMemo,
  useState,
} from "react";

import {
  closeCoffeeLot,
  createCoffeeLot,
  getCoffeeLot,
  getCoffeeLotDashboardRole,
  getCoffeeLots,
  getCoffeeLotSources,
  getCoffeeLotSummary,
  updateCoffeeLot,
} from "@/services/coffee-lot-service";

import {
  getActiveCoffeeSeasonForAllocation,
} from "@/services/cash-allocation-service";

import type {
  ActiveCoffeeSeason,
} from "@/types/cash-allocation";

import type {
  CoffeeLot,
  CoffeeLotSource,
  CoffeeLotSourceType,
  CoffeeLotStatus,
  CoffeeLotSummary,
  DashboardRole,
} from "@/types/coffee-lot";

const emptySummary: CoffeeLotSummary = {
  total_lots: 0,
  active_lots: 0,
  closed_lots: 0,
  initial_weight_kg: "0.00",
  current_weight_kg: "0.00",
};

const inputClass =
  "h-11 w-full rounded-lg border border-slate-400 bg-white px-3 text-sm font-medium text-slate-950 outline-none placeholder:text-slate-600 focus:border-[#075b38] focus:ring-1 focus:ring-[#075b38]";

const textareaClass =
  "min-h-24 w-full rounded-lg border border-slate-400 bg-white px-3 py-2.5 text-sm font-medium text-slate-950 outline-none placeholder:text-slate-600 focus:border-[#075b38] focus:ring-1 focus:ring-[#075b38]";

type LotForm = {
  source: string;
  bagCount: string;
  storageLocation: string;
  lotDate: string;
  notes: string;
};

const emptyForm: LotForm = {
  source: "",
  bagCount: "",
  storageLocation: "",
  lotDate: "",
  notes: "",
};

export default function CoffeeLotManagement() {
  const [items, setItems] =
    useState<CoffeeLot[]>([]);

  const [summary, setSummary] =
    useState<CoffeeLotSummary>(
      emptySummary,
    );

  const [season, setSeason] =
    useState<ActiveCoffeeSeason | null>(
      null,
    );

  const [role, setRole] =
    useState<DashboardRole>("");

  const [sources, setSources] =
    useState<CoffeeLotSource[]>([]);

  const [search, setSearch] =
    useState("");

  const [status, setStatus] =
    useState("");

  const [sourceType, setSourceType] =
    useState("");

  const [filters, setFilters] =
    useState({
      search: "",
      status: "",
      sourceType: "",
    });

  const [page, setPage] =
    useState(1);

  const [lastPage, setLastPage] =
    useState(1);

  const [total, setTotal] =
    useState(0);

  const [loading, setLoading] =
    useState(true);

  const [busy, setBusy] =
    useState(false);

  const [error, setError] =
    useState("");

  const [success, setSuccess] =
    useState("");

  const [createOpen, setCreateOpen] =
    useState(false);

  const [editing, setEditing] =
    useState<CoffeeLot | null>(null);

  const [viewing, setViewing] =
    useState<CoffeeLot | null>(null);

  const [form, setForm] =
    useState<LotForm>(emptyForm);

  const canManage = role === "admin";

  useEffect(() => {
    async function prepare() {
      try {
        const [
          currentRole,
          activeSeason,
        ] = await Promise.all([
          getCoffeeLotDashboardRole(),
          getActiveCoffeeSeasonForAllocation(),
        ]);

        setRole(currentRole);
        setSeason(activeSeason);

        if (currentRole === "admin") {
          setSources(
            await getCoffeeLotSources(),
          );
        }
      } catch (error) {
        setError(
          errorMessage(error),
        );
      }
    }

    void prepare();
  }, []);

  const load = useCallback(
    async () => {
      setLoading(true);
      setError("");

      try {
        const [list, totals] =
          await Promise.all([
            getCoffeeLots({
              search:
                filters.search ||
                undefined,

              status:
                filters.status
                  ? (
                      filters.status as CoffeeLotStatus
                    )
                  : undefined,

              source_type:
                filters.sourceType
                  ? (
                      filters.sourceType as CoffeeLotSourceType
                    )
                  : undefined,

              coffee_season_id:
                season?.id,

              page,
              per_page: 15,
            }),

            getCoffeeLotSummary(
              season?.id,
            ),
          ]);

        setItems(list.items);
        setSummary(totals);

        setTotal(
          list.pagination.total,
        );

        setLastPage(
          Math.max(
            list.pagination.last_page,
            1,
          ),
        );
      } catch (error) {
        setError(
          errorMessage(error),
        );
      } finally {
        setLoading(false);
      }
    },
    [
      filters,
      page,
      season?.id,
    ],
  );

  useEffect(() => {
    void load();
  }, [load]);

  const selectedSource =
    useMemo(() => {
      if (!form.source) {
        return null;
      }

      return sources.find(
        (source) =>
          sourceKey(source) ===
          form.source,
      ) ?? null;
    }, [form.source, sources]);

  function applyFilters() {
    setFilters({
      search: search.trim(),
      status,
      sourceType,
    });

    setPage(1);
  }

  function resetFilters() {
    setSearch("");
    setStatus("");
    setSourceType("");

    setFilters({
      search: "",
      status: "",
      sourceType: "",
    });

    setPage(1);
  }

  async function refreshSources() {
    if (!canManage) {
      return;
    }

    setSources(
      await getCoffeeLotSources(),
    );
  }

  async function openCreate() {
    setError("");
    setSuccess("");

    try {
      await refreshSources();

      setForm({
        ...emptyForm,
        lotDate: today(),
      });

      setCreateOpen(true);
    } catch (error) {
      setError(
        errorMessage(error),
      );
    }
  }

  async function openView(
    lot: CoffeeLot,
  ) {
    try {
      setViewing(
        await getCoffeeLot(lot.id),
      );
    } catch (error) {
      setError(
        errorMessage(error),
      );
    }
  }

  function openEdit(
    lot: CoffeeLot,
  ) {
    setEditing(lot);

    setForm({
      source: "",
      bagCount:
        lot.bag_count
          ? String(lot.bag_count)
          : "",
      storageLocation:
        lot.storage_location ?? "",
      lotDate: lot.lot_date,
      notes: lot.notes ?? "",
    });
  }

  async function submitCreate() {
    if (!selectedSource) {
      setError(
        "Select an eligible coffee source.",
      );
      return;
    }

    if (!form.lotDate) {
      setError(
        "Lot date is required.",
      );
      return;
    }

    setBusy(true);
    setError("");

    try {
      await createCoffeeLot({
        source_type:
          selectedSource.source_type,

        source_id:
          selectedSource.source_id,

        bag_count:
          form.bagCount
            ? Number(form.bagCount)
            : undefined,

        storage_location:
          form.storageLocation.trim()
          || undefined,

        lot_date:
          form.lotDate,

        notes:
          form.notes.trim()
          || undefined,
      });

      setCreateOpen(false);
      setForm(emptyForm);

      setSuccess(
        "Coffee lot created successfully.",
      );

      await Promise.all([
        load(),
        refreshSources(),
      ]);
    } catch (error) {
      setError(
        errorMessage(error),
      );
    } finally {
      setBusy(false);
    }
  }

  async function submitEdit() {
    if (!editing) {
      return;
    }

    setBusy(true);
    setError("");

    try {
      await updateCoffeeLot(
        editing.id,
        {
          bag_count:
            form.bagCount
              ? Number(form.bagCount)
              : undefined,

          storage_location:
            form.storageLocation.trim()
            || undefined,

          notes:
            form.notes.trim()
            || undefined,
        },
      );

      setEditing(null);

      setSuccess(
        "Coffee lot updated successfully.",
      );

      await load();
    } catch (error) {
      setError(
        errorMessage(error),
      );
    } finally {
      setBusy(false);
    }
  }

  async function closeLot(
    lot: CoffeeLot,
  ) {
    const confirmed =
      window.confirm(
        `Close ${lot.lot_code}? This lot will no longer be editable.`,
      );

    if (!confirmed) {
      return;
    }

    setBusy(true);
    setError("");

    try {
      await closeCoffeeLot(
        lot.id,
      );

      setSuccess(
        `${lot.lot_code} closed successfully.`,
      );

      await load();
    } catch (error) {
      setError(
        errorMessage(error),
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="space-y-5 text-slate-950">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 className="text-3xl font-extrabold">
            Coffee Lots / Batches
          </h1>

          <p className="mt-1 text-sm font-medium text-slate-700">
            Track received coffee as
            traceable lots from source,
            storage and later processing.
          </p>
        </div>

        {canManage && (
          <button
            type="button"
            onClick={() =>
              void openCreate()
            }
            className="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-[#075b38] px-5 text-sm font-bold text-white hover:bg-[#064a2f]"
          >
            <Plus size={17} />
            Create Coffee Lot
          </button>
        )}
      </div>

      <div className="flex flex-col gap-3 rounded-xl border border-[#d9c9ae] bg-[#fffaf2] px-4 py-3 md:flex-row md:items-center md:justify-between">
        <div>
          <p className="text-xs font-extrabold uppercase text-[#80570f]">
            Active Coffee Season
          </p>

          <p className="mt-1 font-bold">
            {season
              ? `${season.name} · ${season.code}`
              : "No active coffee season"}
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Warehouse
            size={18}
            className="text-[#80570f]"
          />

          <span className="text-sm font-bold text-slate-800">
            Inventory traceability
          </span>
        </div>
      </div>

      <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <Metric
          label="Total Lots"
          value={String(
            summary.total_lots,
          )}
        />

        <Metric
          label="Active Lots"
          value={String(
            summary.active_lots,
          )}
          strong
        />

        <Metric
          label="Received Weight"
          value={`${number(
            summary.initial_weight_kg,
          )} Kg`}
        />

        <Metric
          label="Current Active Weight"
          value={`${number(
            summary.current_weight_kg,
          )} Kg`}
          strong
        />
      </div>

      <div className="rounded-xl border border-slate-300 bg-white p-4 shadow-sm">
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
          <Field label="Search">
            <div className="relative">
              <Search
                size={16}
                className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-700"
              />

              <input
                value={search}
                onChange={(event) =>
                  setSearch(
                    event.target.value,
                  )
                }
                placeholder="Lot code or storage..."
                className="h-11 w-full rounded-lg border border-slate-400 bg-white pl-9 pr-3 text-sm font-medium text-slate-950 outline-none placeholder:text-slate-600 focus:border-[#075b38] focus:ring-1 focus:ring-[#075b38]"
              />
            </div>
          </Field>

          <Field label="Status">
            <select
              value={status}
              onChange={(event) =>
                setStatus(
                  event.target.value,
                )
              }
              className={inputClass}
            >
              <option value="">
                All Statuses
              </option>
              <option value="active">
                Active
              </option>
              <option value="closed">
                Closed
              </option>
            </select>
          </Field>

          <Field label="Source">
            <select
              value={sourceType}
              onChange={(event) =>
                setSourceType(
                  event.target.value,
                )
              }
              className={inputClass}
            >
              <option value="">
                All Sources
              </option>

              <option value="factory_reception">
                Factory Reception
              </option>

              <option value="direct_farmer_delivery">
                Direct Farmer Delivery
              </option>
            </select>
          </Field>

          <div className="flex items-end">
            <button
              type="button"
              onClick={applyFilters}
              className="h-11 w-full rounded-lg bg-[#075b38] px-4 text-sm font-bold text-white hover:bg-[#064a2f]"
            >
              Filter
            </button>
          </div>

          <div className="flex items-end">
            <button
              type="button"
              onClick={resetFilters}
              className="flex h-11 w-full items-center justify-center gap-2 rounded-lg border border-slate-400 bg-white px-4 text-sm font-bold hover:bg-slate-100"
            >
              <RotateCcw size={15} />
              Reset
            </button>
          </div>
        </div>
      </div>

      {error && (
        <Notice
          type="error"
          text={error}
        />
      )}

      {success && (
        <Notice
          type="success"
          text={success}
        />
      )}

      <section className="overflow-hidden rounded-xl border border-slate-300 bg-white shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[1200px]">
            <thead className="bg-[#f6f1e8]">
              <tr className="border-b border-slate-300 text-left text-xs font-extrabold uppercase text-slate-800">
                <th className="px-4 py-4">
                  Lot
                </th>
                <th className="px-4 py-4">
                  Source
                </th>
                <th className="px-4 py-4">
                  Coffee Type
                </th>
                <th className="px-4 py-4">
                  Initial Weight
                </th>
                <th className="px-4 py-4">
                  Current Weight
                </th>
                <th className="px-4 py-4">
                  Bags
                </th>
                <th className="px-4 py-4">
                  Storage
                </th>
                <th className="px-4 py-4">
                  Stage
                </th>
                <th className="px-4 py-4">
                  Status
                </th>
                <th className="px-4 py-4 text-center">
                  Actions
                </th>
              </tr>
            </thead>

            <tbody>
              {loading ? (
                <tr>
                  <td
                    colSpan={10}
                    className="py-20"
                  >
                    <LoaderCircle
                      size={30}
                      className="mx-auto animate-spin text-[#075b38]"
                    />
                  </td>
                </tr>
              ) : items.length ? (
                items.map((lot) => (
                  <tr
                    key={lot.id}
                    className="border-b border-slate-200 text-sm text-slate-900 hover:bg-[#fffdf8]"
                  >
                    <td className="px-4 py-4">
                      <p className="font-extrabold text-[#80570f]">
                        {lot.lot_code}
                      </p>

                      <p className="mt-1 text-xs font-medium text-slate-700">
                        {formatDate(
                          lot.lot_date,
                        )}
                      </p>
                    </td>

                    <td className="px-4 py-4">
                      <SourceBadge
                        type={
                          lot.source_type
                        }
                      />

                      <p className="mt-1 text-xs font-semibold text-slate-700">
                        Source #{lot.source_id}
                      </p>
                    </td>

                    <td className="px-4 py-4 font-bold capitalize">
                      {label(
                        lot.coffee_type,
                      )}
                    </td>

                    <td className="px-4 py-4 font-semibold">
                      {number(
                        lot.initial_weight_kg,
                      )}{" "}
                      Kg
                    </td>

                    <td className="px-4 py-4 font-extrabold">
                      {number(
                        lot.current_weight_kg,
                      )}{" "}
                      Kg
                    </td>

                    <td className="px-4 py-4 font-semibold">
                      {lot.bag_count ??
                        "—"}
                    </td>

                    <td className="px-4 py-4 font-semibold">
                      {lot.storage_location ??
                        "—"}
                    </td>

                    <td className="px-4 py-4">
                      <span className="rounded-md bg-[#f6f1e8] px-2.5 py-1 text-xs font-extrabold capitalize text-[#684717]">
                        {label(
                          lot.processing_stage,
                        )}
                      </span>
                    </td>

                    <td className="px-4 py-4">
                      <StatusBadge
                        status={
                          lot.status
                        }
                      />
                    </td>

                    <td className="px-4 py-4">
                      <div className="flex justify-center gap-2">
                        <IconButton
                          title="View"
                          onClick={() =>
                            void openView(
                              lot,
                            )
                          }
                        >
                          <Eye size={15} />
                        </IconButton>

                        {canManage &&
                          lot.status ===
                            "active" && (
                            <>
                              <IconButton
                                title="Edit"
                                onClick={() =>
                                  openEdit(
                                    lot,
                                  )
                                }
                              >
                                <Pencil
                                  size={
                                    15
                                  }
                                />
                              </IconButton>

                              <button
                                type="button"
                                disabled={busy}
                                onClick={() =>
                                  void closeLot(
                                    lot,
                                  )
                                }
                                className="h-9 rounded-lg border border-red-300 bg-white px-3 text-xs font-bold text-red-800 hover:bg-red-50 disabled:opacity-50"
                              >
                                Close Lot
                              </button>
                            </>
                          )}
                      </div>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td
                    colSpan={10}
                    className="py-16 text-center"
                  >
                    <Boxes
                      size={35}
                      className="mx-auto text-slate-500"
                    />

                    <p className="mt-3 text-sm font-bold text-slate-800">
                      No coffee lots
                      found.
                    </p>
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        <div className="flex items-center justify-between border-t border-slate-300 px-4 py-4">
          <p className="text-sm font-medium text-slate-700">
            {total} lots · Page{" "}
            <b className="text-slate-950">
              {page}
            </b>{" "}
            of{" "}
            <b className="text-slate-950">
              {lastPage}
            </b>
          </p>

          <div className="flex gap-2">
            <IconButton
              title="Previous"
              disabled={page <= 1}
              onClick={() =>
                setPage(
                  Math.max(
                    1,
                    page - 1,
                  ),
                )
              }
            >
              <ChevronLeft
                size={17}
              />
            </IconButton>

            <IconButton
              title="Next"
              disabled={
                page >= lastPage
              }
              onClick={() =>
                setPage(page + 1)
              }
            >
              <ChevronRight
                size={17}
              />
            </IconButton>
          </div>
        </div>
      </section>

      {createOpen && (
        <Modal>
          <ModalCard
            title="Create Coffee Lot"
            subtitle="Create a traceable lot from an eligible confirmed coffee source."
            close={() =>
              setCreateOpen(false)
            }
          >
            <div className="space-y-4">
              <Field label="Coffee Source">
                <select
                  value={form.source}
                  onChange={(
                    event,
                  ) =>
                    setForm({
                      ...form,
                      source:
                        event.target
                          .value,
                    })
                  }
                  className={inputClass}
                >
                  <option value="">
                    Select source
                  </option>

                  {sources.map(
                    (source) => (
                      <option
                        key={sourceKey(
                          source,
                        )}
                        value={sourceKey(
                          source,
                        )}
                      >
                        {
                          source.reference
                        }{" "}
                        —{" "}
                        {source.label}{" "}
                        —{" "}
                        {number(
                          source.weight_kg,
                        )}{" "}
                        Kg
                      </option>
                    ),
                  )}
                </select>
              </Field>

              {selectedSource && (
                <div className="rounded-lg border border-[#d9c9ae] bg-[#fffaf2] p-4">
                  <p className="text-xs font-extrabold uppercase text-[#80570f]">
                    Selected Source
                  </p>

                  <div className="mt-2 grid gap-2 text-sm md:grid-cols-3">
                    <p>
                      <b>
                        {
                          selectedSource.reference
                        }
                      </b>
                    </p>

                    <p>
                      {
                        selectedSource.label
                      }
                    </p>

                    <p className="font-extrabold">
                      {number(
                        selectedSource.weight_kg,
                      )}{" "}
                      Kg
                    </p>
                  </div>
                </div>
              )}

              <div className="grid gap-4 md:grid-cols-2">
                <Field label="Bag Count">
                  <input
                    type="number"
                    min="1"
                    value={
                      form.bagCount
                    }
                    onChange={(
                      event,
                    ) =>
                      setForm({
                        ...form,
                        bagCount:
                          event.target
                            .value,
                      })
                    }
                    className={
                      inputClass
                    }
                  />
                </Field>

                <Field label="Lot Date">
                  <input
                    type="date"
                    value={
                      form.lotDate
                    }
                    onChange={(
                      event,
                    ) =>
                      setForm({
                        ...form,
                        lotDate:
                          event.target
                            .value,
                      })
                    }
                    className={
                      inputClass
                    }
                  />
                </Field>
              </div>

              <Field label="Storage Location">
                <input
                  value={
                    form.storageLocation
                  }
                  onChange={(
                    event,
                  ) =>
                    setForm({
                      ...form,
                      storageLocation:
                        event.target
                          .value,
                    })
                  }
                  placeholder="Example: Warehouse A - Bay 2"
                  className={
                    inputClass
                  }
                />
              </Field>

              <Field label="Notes">
                <textarea
                  value={form.notes}
                  onChange={(
                    event,
                  ) =>
                    setForm({
                      ...form,
                      notes:
                        event.target
                          .value,
                    })
                  }
                  className={
                    textareaClass
                  }
                />
              </Field>

              <FormActions
                busy={busy}
                cancel={() =>
                  setCreateOpen(
                    false,
                  )
                }
                submit={() =>
                  void submitCreate()
                }
                submitText="Create Lot"
              />
            </div>
          </ModalCard>
        </Modal>
      )}

      {editing && (
        <Modal>
          <ModalCard
            title={`Edit ${editing.lot_code}`}
            subtitle="Update lot storage information without changing source or recorded weight."
            close={() =>
              setEditing(null)
            }
          >
            <div className="space-y-4">
              <div className="rounded-lg border border-slate-300 bg-slate-50 p-4">
                <p className="text-sm font-bold">
                  Recorded weight:{" "}
                  {number(
                    editing.current_weight_kg,
                  )}{" "}
                  Kg
                </p>

                <p className="mt-1 text-xs font-medium text-slate-700">
                  Source and weight are
                  locked for traceability.
                </p>
              </div>

              <Field label="Bag Count">
                <input
                  type="number"
                  min="1"
                  value={
                    form.bagCount
                  }
                  onChange={(
                    event,
                  ) =>
                    setForm({
                      ...form,
                      bagCount:
                        event.target
                          .value,
                    })
                  }
                  className={
                    inputClass
                  }
                />
              </Field>

              <Field label="Storage Location">
                <input
                  value={
                    form.storageLocation
                  }
                  onChange={(
                    event,
                  ) =>
                    setForm({
                      ...form,
                      storageLocation:
                        event.target
                          .value,
                    })
                  }
                  className={
                    inputClass
                  }
                />
              </Field>

              <Field label="Notes">
                <textarea
                  value={form.notes}
                  onChange={(
                    event,
                  ) =>
                    setForm({
                      ...form,
                      notes:
                        event.target
                          .value,
                    })
                  }
                  className={
                    textareaClass
                  }
                />
              </Field>

              <FormActions
                busy={busy}
                cancel={() =>
                  setEditing(null)
                }
                submit={() =>
                  void submitEdit()
                }
                submitText="Save Changes"
              />
            </div>
          </ModalCard>
        </Modal>
      )}

      {viewing && (
        <Modal>
          <ModalCard
            title={
              viewing.lot_code
            }
            subtitle="Coffee lot traceability details."
            close={() =>
              setViewing(null)
            }
          >
            <div className="grid gap-x-6 md:grid-cols-3">
              <Detail
                label="Source"
                value={label(
                  viewing.source_type,
                )}
              />

              <Detail
                label="Source ID"
                value={`#${viewing.source_id}`}
              />

              <Detail
                label="Coffee Type"
                value={label(
                  viewing.coffee_type,
                )}
              />

              <Detail
                label="Initial Weight"
                value={`${number(
                  viewing.initial_weight_kg,
                )} Kg`}
              />

              <Detail
                label="Current Weight"
                value={`${number(
                  viewing.current_weight_kg,
                )} Kg`}
              />

              <Detail
                label="Bag Count"
                value={
                  viewing.bag_count
                    ? String(
                        viewing.bag_count,
                      )
                    : "—"
                }
              />

              <Detail
                label="Stage"
                value={label(
                  viewing.processing_stage,
                )}
              />

              <Detail
                label="Status"
                value={label(
                  viewing.status,
                )}
              />

              <Detail
                label="Storage"
                value={
                  viewing.storage_location ??
                  "—"
                }
              />

              <Detail
                label="Lot Date"
                value={formatDate(
                  viewing.lot_date,
                )}
              />

              <Detail
                label="Created By"
                value={
                  viewing.creator
                    ?.name ?? "—"
                }
              />

              <Detail
                label="Closed By"
                value={
                  viewing.closer
                    ?.name ?? "—"
                }
              />

              <div className="border-b border-slate-300 py-4 md:col-span-3">
                <p className="text-xs font-extrabold uppercase text-slate-700">
                  Notes
                </p>

                <p className="mt-2 text-sm font-medium">
                  {viewing.notes ||
                    "No notes provided."}
                </p>
              </div>
            </div>
          </ModalCard>
        </Modal>
      )}
    </div>
  );
}

function Metric({
  label,
  value,
  strong = false,
}: {
  label: string;
  value: string;
  strong?: boolean;
}) {
  return (
    <div className="rounded-xl border border-slate-300 bg-white p-4 shadow-sm">
      <p className="text-xs font-extrabold uppercase text-slate-700">
        {label}
      </p>

      <p
        className={`mt-2 text-2xl font-extrabold ${
          strong
            ? "text-[#075b38]"
            : "text-slate-950"
        }`}
      >
        {value}
      </p>
    </div>
  );
}

function SourceBadge({
  type,
}: {
  type: CoffeeLotSourceType;
}) {
  const text =
    type === "factory_reception"
      ? "Factory Reception"
      : "Direct Farmer";

  return (
    <span className="rounded-md bg-[#f6f1e8] px-2.5 py-1 text-xs font-extrabold text-[#684717]">
      {text}
    </span>
  );
}

function StatusBadge({
  status,
}: {
  status: CoffeeLotStatus;
}) {
  return (
    <span
      className={`rounded-md px-2.5 py-1 text-xs font-extrabold capitalize ${
        status === "active"
          ? "bg-emerald-100 text-emerald-900"
          : "bg-slate-200 text-slate-800"
      }`}
    >
      {status}
    </span>
  );
}

function Field({
  label: fieldLabel,
  children,
}: {
  label: string;
  children: ReactNode;
}) {
  return (
    <div>
      <label className="mb-2 block text-sm font-bold text-slate-900">
        {fieldLabel}
      </label>

      {children}
    </div>
  );
}

function Detail({
  label: detailLabel,
  value,
}: {
  label: string;
  value: string;
}) {
  return (
    <div className="border-b border-slate-300 py-4">
      <p className="text-xs font-extrabold uppercase text-slate-700">
        {detailLabel}
      </p>

      <p className="mt-1.5 text-sm font-bold">
        {value}
      </p>
    </div>
  );
}

function IconButton({
  title,
  children,
  onClick,
  disabled = false,
}: {
  title: string;
  children: ReactNode;
  onClick: () => void;
  disabled?: boolean;
}) {
  return (
    <button
      type="button"
      title={title}
      disabled={disabled}
      onClick={onClick}
      className="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-400 bg-white hover:bg-slate-100 disabled:opacity-40"
    >
      {children}
    </button>
  );
}

function FormActions({
  busy,
  cancel,
  submit,
  submitText,
}: {
  busy: boolean;
  cancel: () => void;
  submit: () => void;
  submitText: string;
}) {
  return (
    <div className="flex justify-end gap-2 border-t border-slate-300 pt-4">
      <button
        type="button"
        disabled={busy}
        onClick={cancel}
        className="h-10 rounded-lg border border-slate-400 px-4 text-sm font-bold hover:bg-slate-100"
      >
        Cancel
      </button>

      <button
        type="button"
        disabled={busy}
        onClick={submit}
        className="inline-flex h-10 items-center gap-2 rounded-lg bg-[#075b38] px-5 text-sm font-bold text-white hover:bg-[#064a2f] disabled:opacity-50"
      >
        {busy && (
          <LoaderCircle
            size={15}
            className="animate-spin"
          />
        )}

        {submitText}
      </button>
    </div>
  );
}

function Modal({
  children,
}: {
  children: ReactNode;
}) {
  return (
    <div className="fixed inset-0 z-[120] flex items-center justify-center overflow-y-auto bg-black/50 p-4">
      {children}
    </div>
  );
}

function ModalCard({
  title,
  subtitle,
  close,
  children,
}: {
  title: string;
  subtitle: string;
  close: () => void;
  children: ReactNode;
}) {
  return (
    <div className="w-full max-w-3xl rounded-xl bg-white shadow-2xl">
      <div className="flex items-start justify-between border-b border-slate-300 px-6 py-4">
        <div>
          <h2 className="text-xl font-extrabold">
            {title}
          </h2>

          <p className="mt-1 text-sm font-medium text-slate-700">
            {subtitle}
          </p>
        </div>

        <button
          type="button"
          onClick={close}
          className="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-400 hover:bg-slate-100"
        >
          <X size={18} />
        </button>
      </div>

      <div className="max-h-[75vh] overflow-y-auto p-6">
        {children}
      </div>
    </div>
  );
}

function Notice({
  type,
  text,
}: {
  type: "error" | "success";
  text: string;
}) {
  return (
    <div
      className={`rounded-lg border px-4 py-3 text-sm font-semibold ${
        type === "error"
          ? "border-red-300 bg-red-50 text-red-900"
          : "border-emerald-300 bg-emerald-50 text-emerald-900"
      }`}
    >
      {text}
    </div>
  );
}

function sourceKey(
  source: CoffeeLotSource,
) {
  return `${source.source_type}:${source.source_id}`;
}

function number(
  value: string | number,
) {
  const parsed =
    Number(value);

  return new Intl.NumberFormat(
    "en-US",
    {
      maximumFractionDigits: 2,
    },
  ).format(
    Number.isFinite(parsed)
      ? parsed
      : 0,
  );
}

function label(
  value: string,
) {
  return value
    .replaceAll("_", " ")
    .replace(/\b\w/g, (letter) =>
      letter.toUpperCase(),
    );
}

function formatDate(
  value: string,
) {
  const date =
    new Date(value);

  if (
    Number.isNaN(
      date.getTime(),
    )
  ) {
    return value;
  }

  return new Intl.DateTimeFormat(
    "en-GB",
    {
      dateStyle: "medium",
    },
  ).format(date);
}

function today() {
  const date =
    new Date();

  const offset =
    date.getTimezoneOffset();

  return new Date(
    date.getTime() -
      offset * 60000,
  )
    .toISOString()
    .slice(0, 10);
}

function errorMessage(
  error: unknown,
) {
  return error instanceof Error
    ? error.message
    : "Something went wrong.";
}
