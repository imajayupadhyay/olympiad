<template>
  <AdminLayout title="School Management" subtitle="Maintain school leads and coordinator contacts">
    <div v-if="$page.props.flash?.success" class="mb-5 flex items-center gap-3 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl">
      {{ $page.props.flash.success }}
    </div>
    <div v-if="$page.props.flash?.error" class="mb-5 flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl">
      {{ $page.props.flash.error }}
    </div>

    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4 mb-6">
      <div class="flex items-center gap-2 flex-wrap">
        <span class="bg-primary/10 text-primary text-sm font-semibold px-3 py-1.5 rounded-lg font-number">
          {{ summary.matched.toLocaleString() }} Schools
        </span>
        <span v-if="hasFilters" class="bg-accent/10 text-accent text-xs font-semibold px-2.5 py-1.5 rounded-lg">
          Filtered
        </span>
      </div>
      <div class="flex flex-wrap gap-2">
        <a
          :href="excelUrl"
          :aria-disabled="!canExportExcel"
          :title="canExportExcel ? 'Export school data' : `Excel exports support up to ${exportLimits.excel.toLocaleString()} rows`"
          class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border transition-colors shadow-sm"
          :class="canExportExcel ? 'bg-white border-success/30 text-success hover:bg-success/5' : 'bg-gray-100 border-gray-200 text-gray-400 pointer-events-none'"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M5 19h14"/>
          </svg>
          Export Excel
        </a>
        <button
          type="button"
          @click="openCreate"
          class="inline-flex items-center gap-2 bg-primary text-white px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors shadow-sm"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
          </svg>
          Add New
        </button>
      </div>
    </div>

    <div class="grid grid-cols-2 xl:grid-cols-5 gap-3 mb-6">
      <div v-for="stat in statCards" :key="stat.label" class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4 min-w-0">
        <div class="flex items-center gap-2 mb-2">
          <span class="w-2 h-2 rounded-full shrink-0" :class="stat.dot"></span>
          <p class="text-[11px] font-semibold uppercase text-text-muted truncate">{{ stat.label }}</p>
        </div>
        <p class="font-number text-2xl font-bold truncate" :class="stat.color">{{ stat.value }}</p>
      </div>
    </div>

    <section class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-5" aria-labelledby="school-filters-heading">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
        <h2 id="school-filters-heading" class="font-heading font-bold text-text-main text-sm">Filters</h2>
        <button v-if="hasFilters" type="button" @click="clearFilters" class="text-xs font-semibold text-danger hover:text-red-700">Clear all</button>
      </div>

      <form class="p-5" @submit.prevent="applyFilters">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
          <div class="xl:col-span-2 relative">
            <label class="filter-label" for="school-search">Search</label>
            <svg class="absolute left-3 top-[2.45rem] w-4 h-4 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input
              id="school-search"
              v-model="filterForm.search"
              type="text"
              placeholder="Code, school, email, phone, coordinator…"
              class="filter-control pl-9"
            />
          </div>

          <label class="block">
            <span class="filter-label">State</span>
            <select v-model="filterForm.state" class="filter-control">
              <option value="">All states</option>
              <option v-for="state in states" :key="state" :value="state">{{ state }}</option>
            </select>
          </label>

          <label class="block">
            <span class="filter-label">District</span>
            <select v-model="filterForm.district" class="filter-control">
              <option value="">All districts</option>
              <option v-for="district in districts" :key="district" :value="district">{{ district }}</option>
            </select>
          </label>

          <label class="block">
            <span class="filter-label">City</span>
            <select v-model="filterForm.city" class="filter-control">
              <option value="">All cities</option>
              <option v-for="city in cities" :key="city" :value="city">{{ city }}</option>
            </select>
          </label>

          <label class="block">
            <span class="filter-label">Status</span>
            <select v-model="filterForm.status" class="filter-control">
              <option value="">Any status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </label>

          <label class="block">
            <span class="filter-label">Coordinators</span>
            <select v-model="filterForm.has_coordinators" class="filter-control">
              <option value="">Any contacts</option>
              <option value="yes">Has coordinators</option>
              <option value="no">No coordinators</option>
            </select>
          </label>

          <label class="block">
            <span class="filter-label">Added from</span>
            <input v-model="filterForm.date_from" type="date" class="filter-control" />
          </label>

          <label class="block">
            <span class="filter-label">Added to</span>
            <input v-model="filterForm.date_to" type="date" :min="filterForm.date_from || undefined" class="filter-control" />
          </label>
        </div>

        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4 mt-5 pt-4 border-t border-gray-100">
          <div class="flex flex-wrap gap-3">
            <label class="block">
              <span class="filter-label">Sort by</span>
              <select v-model="filterForm.sort" class="filter-control min-w-44">
                <option value="created_at">Added date</option>
                <option value="name">School name</option>
                <option value="school_code">School code</option>
                <option value="state">State</option>
                <option value="city">City</option>
                <option value="coordinators">Coordinator count</option>
              </select>
            </label>
            <label class="block">
              <span class="filter-label">Order</span>
              <select v-model="filterForm.direction" class="filter-control min-w-32">
                <option value="desc">Descending</option>
                <option value="asc">Ascending</option>
              </select>
            </label>
            <label class="block">
              <span class="filter-label">Rows</span>
              <select v-model="filterForm.per_page" class="filter-control min-w-24">
                <option value="20">20</option>
                <option value="50">50</option>
                <option value="100">100</option>
              </select>
            </label>
          </div>
          <button type="submit" class="inline-flex items-center justify-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M7 12h10m-7 7h4"/>
            </svg>
            Apply Filters
          </button>
        </div>
      </form>
    </section>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
        <h2 class="font-heading font-bold text-text-main text-sm">School Records</h2>
        <span class="text-xs text-text-muted">{{ schools.total.toLocaleString() }} records</span>
      </div>

      <div v-if="schools.data.length === 0" class="py-20 px-5 text-center">
        <div class="w-16 h-16 rounded-2xl bg-primary/10 flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 21V7l8-4 8 4v14M8 21v-7h8v7M7 10h.01M12 10h.01M17 10h.01"/>
          </svg>
        </div>
        <p class="font-heading font-bold text-text-main text-base mb-1">
          {{ hasFilters ? 'No schools match your filters' : 'No managed schools yet' }}
        </p>
        <p class="text-text-muted text-sm mb-5">
          {{ hasFilters ? 'Adjust the filters to broaden the result.' : 'Add the first school record to begin your lead database.' }}
        </p>
        <button v-if="!hasFilters" type="button" @click="openCreate" class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
          </svg>
          Add New
        </button>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[1180px] text-sm">
          <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
              <th class="table-head w-12">#</th>
              <th class="table-head">School</th>
              <th class="table-head">Location</th>
              <th class="table-head">Primary Contact</th>
              <th class="table-head">Head Contact</th>
              <th class="table-head">Coordinators</th>
              <th class="table-head">Status</th>
              <th class="table-head text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="(school, index) in schools.data" :key="school.id" class="hover:bg-gray-50/70 transition-colors align-top">
              <td class="table-cell text-xs text-text-muted font-number">{{ schools.from + index }}</td>
              <td class="table-cell">
                <div class="flex items-start gap-3">
                  <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-heading font-bold shrink-0">
                    {{ initials(school.name) }}
                  </div>
                  <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                      <p class="font-semibold text-text-main max-w-72 truncate">{{ school.name }}</p>
                      <span class="font-number text-[11px] font-bold text-primary bg-primary/10 px-2 py-0.5 rounded">{{ school.school_code }}</span>
                    </div>
                    <p class="text-xs text-text-muted mt-1 max-w-80 line-clamp-2">{{ school.address || 'No address recorded' }}</p>
                  </div>
                </div>
              </td>
              <td class="table-cell">
                <p class="text-xs font-semibold text-text-main max-w-48 truncate">{{ locationLine(school) }}</p>
                <p class="text-xs text-text-muted mt-1 max-w-48 truncate">{{ school.district || 'No district' }}</p>
                <p v-if="school.pin_code" class="font-number text-[11px] text-text-muted mt-0.5">PIN {{ school.pin_code }}</p>
              </td>
              <td class="table-cell">
                <a v-if="school.email" :href="`mailto:${school.email}`" class="block text-xs text-primary font-semibold hover:text-accent max-w-56 truncate">{{ school.email }}</a>
                <span v-else class="block text-xs text-text-muted">No email</span>
                <a v-if="school.mobile" :href="`tel:${school.mobile}`" class="block text-xs text-text-main mt-1 font-number">{{ school.mobile }}</a>
                <span v-else class="block text-xs text-text-muted mt-1">No mobile</span>
              </td>
              <td class="table-cell">
                <a v-if="school.head_phone" :href="`tel:${school.head_phone}`" class="font-number text-xs text-text-main">{{ school.head_phone }}</a>
                <span v-else class="text-xs text-text-muted">—</span>
              </td>
              <td class="table-cell max-w-72">
                <div v-if="school.coordinators.length" class="space-y-1.5">
                  <button
                    v-for="coordinator in school.coordinators.slice(0, 2)"
                    :key="coordinator.id"
                    type="button"
                    @click="viewTarget = school"
                    class="block text-left max-w-64 rounded-lg bg-royal/10 text-royal px-2.5 py-1.5 hover:bg-royal/15 transition-colors"
                  >
                    <span class="block text-[11px] font-semibold truncate">{{ coordinator.name }}</span>
                    <span class="block text-[10px] text-royal/80 truncate">{{ coordinator.designation || coordinator.phone || coordinator.email || 'Coordinator' }}</span>
                  </button>
                  <button v-if="school.coordinators.length > 2" type="button" @click="viewTarget = school" class="text-[11px] font-semibold text-text-muted hover:text-primary">
                    +{{ school.coordinators.length - 2 }} more
                  </button>
                </div>
                <span v-else class="text-xs text-text-muted">No coordinators</span>
              </td>
              <td class="table-cell">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full" :class="school.is_active ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger'">
                  <span class="w-1.5 h-1.5 rounded-full" :class="school.is_active ? 'bg-success' : 'bg-danger'"></span>
                  {{ school.is_active ? 'Active' : 'Inactive' }}
                </span>
                <p class="text-[11px] text-text-muted mt-1.5 font-number">{{ formatDate(school.created_at) }}</p>
              </td>
              <td class="table-cell">
                <div class="flex items-center justify-end gap-1.5 whitespace-nowrap">
                  <button @click="viewTarget = school" class="action-link text-primary hover:text-accent hover:bg-primary/5">View</button>
                  <button @click="openEdit(school)" class="action-link text-text-muted hover:text-text-main hover:bg-gray-100">Edit</button>
                  <button @click="openToggle(school)" class="action-link" :class="school.is_active ? 'text-amber-600 hover:bg-amber-50' : 'text-success hover:bg-success/5'">
                    {{ school.is_active ? 'Disable' : 'Enable' }}
                  </button>
                  <button @click="deleteTarget = school" class="action-link text-danger hover:text-red-700 hover:bg-danger/5">Delete</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="schools.last_page > 1" class="px-5 py-4 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <p class="text-text-muted text-xs">Showing {{ schools.from }}-{{ schools.to }} of {{ schools.total.toLocaleString() }} schools</p>
        <div class="flex flex-wrap gap-1">
          <Link
            v-for="link in schools.links"
            :key="link.label"
            :href="link.url || '#'"
            :class="[
              'px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors',
              link.active ? 'bg-primary text-white' : 'text-text-muted hover:bg-gray-100',
              !link.url ? 'opacity-40 pointer-events-none' : '',
            ]"
            v-html="link.label"
            preserve-scroll
          />
        </div>
      </div>
    </div>

    <datalist id="state-options">
      <option v-for="state in states" :key="state" :value="state" />
    </datalist>
    <datalist id="district-options">
      <option v-for="district in districts" :key="district" :value="district" />
    </datalist>
    <datalist id="city-options">
      <option v-for="city in cities" :key="city" :value="city" />
    </datalist>

    <!-- View modal -->
    <div v-if="viewTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-auto" style="background:rgba(0,0,0,.5)" @click.self="viewTarget = null">
      <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full my-8 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <h3 class="font-heading font-bold text-text-main text-lg truncate">{{ viewTarget.name }}</h3>
              <span class="font-number text-[11px] font-bold text-primary bg-primary/10 px-2 py-0.5 rounded">{{ viewTarget.school_code }}</span>
            </div>
            <p class="text-text-muted text-xs mt-1">{{ locationLine(viewTarget) }}</p>
          </div>
          <button @click="viewTarget = null" class="text-text-muted hover:text-text-main p-1 rounded-lg hover:bg-gray-100">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <div class="p-6 grid grid-cols-1 lg:grid-cols-3 gap-5">
          <div class="lg:col-span-2 space-y-4">
            <div>
              <p class="detail-label">Address</p>
              <p class="detail-box whitespace-pre-wrap">{{ viewTarget.address || '—' }}</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <p class="detail-label">Email</p>
                <a v-if="viewTarget.email" :href="`mailto:${viewTarget.email}`" class="detail-link">{{ viewTarget.email }}</a>
                <p v-else class="detail-box">—</p>
              </div>
              <div>
                <p class="detail-label">Mobile</p>
                <a v-if="viewTarget.mobile" :href="`tel:${viewTarget.mobile}`" class="detail-link font-number">{{ viewTarget.mobile }}</a>
                <p v-else class="detail-box">—</p>
              </div>
              <div>
                <p class="detail-label">Head Contact</p>
                <a v-if="viewTarget.head_phone" :href="`tel:${viewTarget.head_phone}`" class="detail-link font-number">{{ viewTarget.head_phone }}</a>
                <p v-else class="detail-box">—</p>
              </div>
              <div>
                <p class="detail-label">PIN Code</p>
                <p class="detail-box font-number">{{ viewTarget.pin_code || '—' }}</p>
              </div>
            </div>
          </div>

          <div>
            <div class="flex items-center justify-between mb-2">
              <p class="detail-label mb-0">Coordinators</p>
              <span class="font-number text-xs text-text-muted">{{ viewTarget.coordinators_count }}</span>
            </div>
            <div v-if="viewTarget.coordinators.length" class="space-y-2 max-h-80 overflow-auto pr-1">
              <div v-for="coordinator in viewTarget.coordinators" :key="coordinator.id" class="rounded-xl border border-gray-100 bg-gray-50 p-3">
                <p class="font-semibold text-sm text-text-main">{{ coordinator.name }}</p>
                <p class="text-xs text-text-muted mt-0.5">{{ coordinator.designation || 'Coordinator' }}</p>
                <div class="mt-2 space-y-1">
                  <a v-if="coordinator.email" :href="`mailto:${coordinator.email}`" class="block text-xs text-primary font-semibold truncate">{{ coordinator.email }}</a>
                  <a v-if="coordinator.phone" :href="`tel:${coordinator.phone}`" class="block text-xs text-text-main font-number">{{ coordinator.phone }}</a>
                </div>
              </div>
            </div>
            <p v-else class="detail-box">—</p>
          </div>
        </div>

        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3">
          <button @click="viewTarget = null" class="bg-white border border-gray-200 text-text-main px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-100 transition-colors">Close</button>
          <button @click="openEditFromView" class="bg-primary text-white px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors">Edit School</button>
        </div>
      </div>
    </div>

    <!-- Create / Edit modal -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-auto" style="background:rgba(0,0,0,.5)" @click.self="closeForm">
      <div class="bg-white rounded-2xl shadow-2xl max-w-5xl w-full my-8 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-start justify-between gap-4">
          <div>
            <h3 class="font-heading font-bold text-text-main text-lg">{{ form.id ? 'Edit School' : 'Add New School' }}</h3>
            <p class="text-text-muted text-xs mt-1">School details and coordinator contacts</p>
          </div>
          <button type="button" @click="closeForm" class="text-text-muted hover:text-text-main p-1 rounded-lg hover:bg-gray-100">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <form @submit.prevent="submit" class="max-h-[78vh] overflow-auto">
          <div class="p-6 space-y-6">
            <section>
              <div class="flex items-center justify-between gap-3 mb-4">
                <h4 class="font-heading font-bold text-text-main text-sm">School Details</h4>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                  <input v-model="form.is_active" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-accent focus:ring-accent" />
                  <span class="text-sm text-text-main font-medium">Active</span>
                </label>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <label class="block">
                  <span class="form-label">School Code</span>
                  <input v-model="form.school_code" type="text" class="form-control font-number uppercase tracking-wide" placeholder="SCH-001" />
                  <span v-if="form.errors.school_code" class="form-error">{{ form.errors.school_code }}</span>
                </label>

                <label class="block md:col-span-2">
                  <span class="form-label">School Name</span>
                  <SchoolAutocomplete
                    id="admin-school-name"
                    :model-value="form.name"
                    :address="form.address"
                    search-route-name="admin.schools.search"
                    variant="admin"
                    placeholder="Start typing to search existing schools..."
                    @update:model-value="updateSchoolName"
                    @update:address="form.address = $event"
                    @selected="selectSuggestedSchool"
                  />
                  <span v-if="form.source_school_id" class="block text-success text-xs mt-1">
                    Existing school selected. Saving will add it to School Management.
                  </span>
                  <span v-if="form.errors.name" class="form-error">{{ form.errors.name }}</span>
                  <span v-if="form.errors.source_school_id" class="form-error">{{ form.errors.source_school_id }}</span>
                </label>

                <label class="block">
                  <span class="form-label">State</span>
                  <input v-model="form.state" type="text" list="state-options" class="form-control" placeholder="Delhi" />
                  <span v-if="form.errors.state" class="form-error">{{ form.errors.state }}</span>
                </label>

                <label class="block">
                  <span class="form-label">District</span>
                  <input v-model="form.district" type="text" list="district-options" class="form-control" placeholder="South Delhi" />
                  <span v-if="form.errors.district" class="form-error">{{ form.errors.district }}</span>
                </label>

                <label class="block">
                  <span class="form-label">City</span>
                  <input v-model="form.city" type="text" list="city-options" class="form-control" placeholder="New Delhi" />
                  <span v-if="form.errors.city" class="form-error">{{ form.errors.city }}</span>
                </label>

                <label class="block">
                  <span class="form-label">PIN Code</span>
                  <input v-model="form.pin_code" type="text" inputmode="numeric" maxlength="6" class="form-control font-number" placeholder="110001" />
                  <span v-if="form.errors.pin_code" class="form-error">{{ form.errors.pin_code }}</span>
                </label>

                <label class="block">
                  <span class="form-label">Email Address</span>
                  <input v-model="form.email" type="email" class="form-control" placeholder="school@example.com" />
                  <span v-if="form.errors.email" class="form-error">{{ form.errors.email }}</span>
                </label>

                <label class="block">
                  <span class="form-label">Mobile Number</span>
                  <input v-model="form.mobile" type="tel" class="form-control font-number" placeholder="+91 9876543210" />
                  <span v-if="form.errors.mobile" class="form-error">{{ form.errors.mobile }}</span>
                </label>

                <label class="block">
                  <span class="form-label">School Head Contact</span>
                  <input v-model="form.head_phone" type="tel" class="form-control font-number" placeholder="+91 9876543210" />
                  <span v-if="form.errors.head_phone" class="form-error">{{ form.errors.head_phone }}</span>
                </label>

                <label class="block md:col-span-2 xl:col-span-3">
                  <span class="form-label">School Address</span>
                  <textarea v-model="form.address" rows="3" class="form-control resize-y" placeholder="Full school address"></textarea>
                  <span v-if="form.errors.address" class="form-error">{{ form.errors.address }}</span>
                </label>
              </div>
            </section>

            <section class="border-t border-gray-100 pt-5">
              <div class="flex items-center justify-between gap-3 mb-4">
                <div>
                  <h4 class="font-heading font-bold text-text-main text-sm">Coordinator Contacts</h4>
                  <p v-if="coordinatorHasErrors" class="text-danger text-xs mt-1">Check the highlighted coordinator fields.</p>
                </div>
                <button type="button" @click="addCoordinator" class="inline-flex items-center gap-2 bg-royal/10 text-royal px-3 py-2 rounded-xl text-xs font-semibold hover:bg-royal/15 transition-colors">
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                  </svg>
                  Add
                </button>
              </div>

              <div class="space-y-3">
                <div v-for="(coordinator, index) in form.coordinators" :key="index" class="rounded-2xl border border-gray-100 bg-gray-50 p-4">
                  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
                    <label class="block">
                      <span class="form-label">Name</span>
                      <input v-model="coordinator.name" type="text" class="form-control bg-white" placeholder="Coordinator name" />
                      <span v-if="coordinatorError(index, 'name')" class="form-error">{{ coordinatorError(index, 'name') }}</span>
                    </label>
                    <label class="block">
                      <span class="form-label">Email</span>
                      <input v-model="coordinator.email" type="email" class="form-control bg-white" placeholder="name@example.com" />
                      <span v-if="coordinatorError(index, 'email')" class="form-error">{{ coordinatorError(index, 'email') }}</span>
                    </label>
                    <label class="block">
                      <span class="form-label">Phone Number</span>
                      <input v-model="coordinator.phone" type="tel" class="form-control bg-white font-number" placeholder="+91 9876543210" />
                      <span v-if="coordinatorError(index, 'phone')" class="form-error">{{ coordinatorError(index, 'phone') }}</span>
                    </label>
                    <div class="flex gap-2">
                      <label class="block flex-1 min-w-0">
                        <span class="form-label">Designation</span>
                        <input v-model="coordinator.designation" type="text" class="form-control bg-white" placeholder="Principal, teacher…" />
                        <span v-if="coordinatorError(index, 'designation')" class="form-error">{{ coordinatorError(index, 'designation') }}</span>
                      </label>
                      <button
                        type="button"
                        @click="removeCoordinator(index)"
                        class="mt-6 w-10 h-10 rounded-xl bg-white border border-gray-200 text-text-muted hover:text-danger hover:border-danger/30 hover:bg-danger/5 transition-colors shrink-0"
                        aria-label="Remove coordinator"
                      >
                        <svg class="w-4 h-4 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </section>
          </div>

          <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex flex-col sm:flex-row justify-end gap-3">
            <button type="button" @click="closeForm" class="bg-white border border-gray-200 text-text-main px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-100 transition-colors">Cancel</button>
            <button type="submit" :disabled="form.processing" class="bg-primary text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors disabled:opacity-60">
              {{ form.processing ? 'Saving...' : (form.id ? 'Save Changes' : 'Add School') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Toggle status modal -->
    <div v-if="toggleTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.5)">
      <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4" :class="toggleTarget.is_active ? 'bg-amber-50' : 'bg-success/10'">
          <svg class="w-6 h-6" :class="toggleTarget.is_active ? 'text-amber-500' : 'text-success'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path v-if="toggleTarget.is_active" stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
            <path v-else stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
        <h3 class="font-heading font-bold text-text-main text-base mb-2">
          {{ toggleTarget.is_active ? 'Disable school?' : 'Enable school?' }}
        </h3>
        <p class="text-text-muted text-sm mb-5">
          <span class="font-semibold text-text-main">{{ toggleTarget.name }}</span>
          will be marked {{ toggleTarget.is_active ? 'inactive' : 'active' }}.
        </p>
        <div class="flex gap-3">
          <button @click="toggleTarget = null" class="flex-1 bg-gray-100 text-text-main py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors">Cancel</button>
          <button @click="confirmToggle" class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-white transition-colors" :class="toggleTarget.is_active ? 'bg-amber-500 hover:bg-amber-600' : 'bg-success hover:bg-green-700'">
            {{ toggleTarget.is_active ? 'Disable' : 'Enable' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Delete modal -->
    <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.5)">
      <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
        <div class="w-12 h-12 rounded-xl bg-danger/10 flex items-center justify-center mb-4">
          <svg class="w-6 h-6 text-danger" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
          </svg>
        </div>
        <h3 class="font-heading font-bold text-text-main text-base mb-2">Delete school?</h3>
        <p class="text-text-muted text-sm mb-5">
          <span class="font-semibold text-text-main">{{ deleteTarget.name }}</span> and its coordinator contacts will be removed.
        </p>
        <div class="flex gap-3">
          <button @click="deleteTarget = null" class="flex-1 bg-gray-100 text-text-main py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors">Cancel</button>
          <button @click="confirmDelete" class="flex-1 bg-danger text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-red-700 transition-colors">Delete</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import SchoolAutocomplete from '@/Components/SchoolAutocomplete.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  schools: Object,
  states: Array,
  districts: Array,
  cities: Array,
  filters: Object,
  summary: Object,
  exportLimits: Object,
});

const filterDefaults = {
  search: '',
  state: '',
  district: '',
  city: '',
  status: '',
  has_coordinators: '',
  date_from: '',
  date_to: '',
  sort: 'created_at',
  direction: 'desc',
  per_page: '20',
};

const filterForm = reactive(Object.fromEntries(
  Object.entries(filterDefaults).map(([key, fallback]) => [key, props.filters[key] != null ? String(props.filters[key]) : fallback]),
));

const filterKeys = ['search', 'state', 'district', 'city', 'status', 'has_coordinators', 'date_from', 'date_to'];
const hasFilters = computed(() => filterKeys.some(key => filterForm[key]));
const query = computed(() => Object.fromEntries(Object.entries(filterForm).filter(([, value]) => value !== '')));
const exportQuery = computed(() => new URLSearchParams(query.value).toString());
const excelUrl = computed(() => `${route('admin.schools.excel')}${exportQuery.value ? `?${exportQuery.value}` : ''}`);
const canExportExcel = computed(() => props.summary.matched <= props.exportLimits.excel);

const statCards = computed(() => [
  { label: 'Matched', value: props.summary.matched.toLocaleString(), color: 'text-primary', dot: 'bg-primary' },
  { label: 'Active', value: props.summary.active.toLocaleString(), color: 'text-success', dot: 'bg-success' },
  { label: 'Inactive', value: props.summary.inactive.toLocaleString(), color: 'text-danger', dot: 'bg-danger' },
  { label: 'With Contacts', value: props.summary.with_coordinators.toLocaleString(), color: 'text-royal', dot: 'bg-royal' },
  { label: 'States', value: props.summary.states.toLocaleString(), color: 'text-gold-dark', dot: 'bg-gold' },
]);

const applyFilters = () => router.get(route('admin.schools.index'), query.value, {
  preserveState: true,
  preserveScroll: true,
  replace: true,
});

const clearFilters = () => {
  Object.assign(filterForm, filterDefaults);
  applyFilters();
};

const blankCoordinator = () => ({ name: '', email: '', phone: '', designation: '' });
const form = useForm({
  id: null,
  school_code: '',
  name: '',
  address: '',
  state: '',
  district: '',
  city: '',
  pin_code: '',
  email: '',
  mobile: '',
  head_phone: '',
  is_active: true,
  source_school_id: null,
  coordinators: [blankCoordinator()],
});

const showForm = ref(false);
const viewTarget = ref(null);
const toggleTarget = ref(null);
const deleteTarget = ref(null);

const resetForm = () => {
  form.clearErrors();
  form.id = null;
  form.school_code = '';
  form.name = '';
  form.address = '';
  form.state = '';
  form.district = '';
  form.city = '';
  form.pin_code = '';
  form.email = '';
  form.mobile = '';
  form.head_phone = '';
  form.is_active = true;
  form.source_school_id = null;
  form.coordinators = [blankCoordinator()];
};

const openCreate = () => {
  resetForm();
  showForm.value = true;
};

const openEdit = (school) => {
  resetForm();
  form.id = school.id;
  form.school_code = school.school_code || '';
  form.name = school.name || '';
  form.address = school.address || '';
  form.state = school.state || '';
  form.district = school.district || '';
  form.city = school.city || '';
  form.pin_code = school.pin_code || '';
  form.email = school.email || '';
  form.mobile = school.mobile || '';
  form.head_phone = school.head_phone || '';
  form.is_active = !!school.is_active;
  form.source_school_id = null;
  form.coordinators = school.coordinators.length
    ? school.coordinators.map(coordinator => ({
        name: coordinator.name || '',
        email: coordinator.email || '',
        phone: coordinator.phone || '',
        designation: coordinator.designation || '',
      }))
    : [blankCoordinator()];
  showForm.value = true;
};

const openEditFromView = () => {
  const school = viewTarget.value;
  viewTarget.value = null;
  openEdit(school);
};

const closeForm = () => {
  if (form.processing) return;
  showForm.value = false;
  resetForm();
};

const addCoordinator = () => form.coordinators.push(blankCoordinator());
const removeCoordinator = (index) => {
  form.coordinators.splice(index, 1);
  if (form.coordinators.length === 0) form.coordinators.push(blankCoordinator());
};

const updateSchoolName = (value) => {
  form.name = value;
  form.source_school_id = null;
};

const selectSuggestedSchool = (school) => {
  form.source_school_id = school.id;
};

const coordinatorError = (index, field) => form.errors[`coordinators.${index}.${field}`];
const coordinatorHasErrors = computed(() => Object.keys(form.errors).some(key => key.startsWith('coordinators.')));

const submit = () => {
  const options = {
    preserveScroll: true,
    onSuccess: () => {
      showForm.value = false;
      resetForm();
    },
  };

  if (form.id) {
    form.put(route('admin.schools.update', form.id), options);
  } else {
    form.post(route('admin.schools.store'), options);
  }
};

const openToggle = (school) => {
  toggleTarget.value = school;
};

const confirmToggle = () => {
  router.patch(route('admin.schools.toggle', toggleTarget.value.id), {
    is_active: !toggleTarget.value.is_active,
  }, {
    preserveScroll: true,
    onFinish: () => {
      toggleTarget.value = null;
    },
  });
};

const confirmDelete = () => {
  router.delete(route('admin.schools.destroy', deleteTarget.value.id), {
    preserveScroll: true,
    onFinish: () => {
      deleteTarget.value = null;
    },
  });
};

const initials = name => name?.split(/\s+/).map(word => word[0]).slice(0, 2).join('').toUpperCase() || '?';
const locationLine = school => [school.city, school.state].filter(Boolean).join(', ') || 'No location';
const formatDate = value => value
  ? new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
  : '-';
</script>

<style scoped>
.filter-label,
.form-label,
.detail-label {
  display: block;
  margin-bottom: 0.375rem;
  color: #5B6373;
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
}

.filter-control,
.form-control {
  width: 100%;
  min-height: 2.625rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.75rem;
  background: #f9fafb;
  padding: 0.625rem 0.75rem;
  color: #0A1024;
  font-size: 0.8125rem;
}

.filter-control:focus,
.form-control:focus {
  border-color: #131C3D;
  outline: none;
  box-shadow: 0 0 0 2px rgba(19, 28, 61, 0.1);
}

.form-error {
  display: block;
  margin-top: 0.25rem;
  color: #DC2626;
  font-size: 0.75rem;
}

.table-head {
  padding: 0.75rem 1rem;
  color: #5B6373;
  font-size: 0.6875rem;
  font-weight: 600;
  text-align: left;
  text-transform: uppercase;
  white-space: nowrap;
}

.table-cell {
  padding: 1rem;
}

.action-link {
  padding: 0.25rem 0.5rem;
  border-radius: 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
  transition: color 150ms ease, background-color 150ms ease;
}

.detail-box {
  min-height: 2.5rem;
  border: 1px solid #f3f4f6;
  border-radius: 0.75rem;
  background: #f9fafb;
  padding: 0.75rem;
  color: #0A1024;
  font-size: 0.8125rem;
  line-height: 1.5;
}

.detail-link {
  display: block;
  min-height: 2.5rem;
  border: 1px solid #f3f4f6;
  border-radius: 0.75rem;
  background: #f9fafb;
  padding: 0.75rem;
  color: #131C3D;
  font-size: 0.8125rem;
  font-weight: 600;
  word-break: break-word;
}
</style>
