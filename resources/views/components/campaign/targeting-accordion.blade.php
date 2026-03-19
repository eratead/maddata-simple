@props(['campaign'])
@php
    $tr = $campaign->targeting_rules ?? [];
    $locationData = $campaign->locations->map(fn($l) => [
        'name' => $l->name, 'lat' => $l->lat, 'lng' => $l->lng, 'radius_meters' => $l->radius_meters
    ])->values();
    $cities = $tr['cities'] ?? [];
    if (is_string($cities)) $cities = json_decode($cities, true) ?? [];
@endphp

<div class="bg-white border border-gray-200 rounded-xl overflow-hidden"
    x-data="targetingData({
        activeTab: 'demographics',
        genders:      {{ Js::from(old('targeting_rules.genders',      $tr['genders']      ?? [])) }},
        ages:         {{ Js::from(old('targeting_rules.ages',         $tr['ages']         ?? [])) }},
        incomes:      {{ Js::from(old('targeting_rules.incomes',      $tr['incomes']      ?? ['0-195K','195-220K','220-245K','245K+'])) }},
        deviceTypes:  {{ Js::from(old('targeting_rules.device_types', $tr['device_types'] ?? ['Mobile','Tablet'])) }},
        os:           {{ Js::from(old('targeting_rules.os',           $tr['os']           ?? ['iOS','Android','Windows','macOS'])) }},
        connectionTypes: ['WiFi','Cellular'],
        environments: {{ Js::from(old('targeting_rules.environments', $tr['environments'] ?? ['In-App','Mobile Web'])) }},
        days:         {{ Js::from(old('targeting_rules.days',         $tr['days']         ?? ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'])) }},
        timeStart:    {{ Js::from(old('targeting_rules.time_start',   $tr['time_start']   ?? '')) }},
        timeEnd:      {{ Js::from(old('targeting_rules.time_end',     $tr['time_end']     ?? '')) }},
        locations:    {{ Js::from($locationData) }},
        countries:    {{ Js::from(old('targeting_rules.countries',    $tr['countries']    ?? ['Israel'])) }},
        regions:      {{ Js::from(old('targeting_rules.regions',      $tr['regions']      ?? [])) }},
        cities:       {{ Js::from(old('targeting_rules.cities',       $cities)) }},
    })">

    {{-- Hidden inputs for form submission --}}
    <template x-for="g in genders"        :key="'g-'+g"><input type="hidden" name="targeting_rules[genders][]" :value="g"></template>
    <template x-for="a in ages"           :key="'a-'+a"><input type="hidden" name="targeting_rules[ages][]" :value="a"></template>
    <template x-for="i in incomes"        :key="'i-'+i"><input type="hidden" name="targeting_rules[incomes][]" :value="i"></template>
    <template x-for="d in deviceTypes"    :key="'d-'+d"><input type="hidden" name="targeting_rules[device_types][]" :value="d"></template>
    <template x-for="o in os"             :key="'o-'+o"><input type="hidden" name="targeting_rules[os][]" :value="o"></template>
    <template x-for="c in connectionTypes" :key="'c-'+c"><input type="hidden" name="targeting_rules[connection_types][]" :value="c"></template>
    <template x-for="e in environments"   :key="'e-'+e"><input type="hidden" name="targeting_rules[environments][]" :value="e"></template>
    <template x-for="day in days"         :key="'day-'+day"><input type="hidden" name="targeting_rules[days][]" :value="day"></template>
    <template x-for="(loc,i) in locations" :key="'loc-'+i">
        <span>
            <input type="hidden" :name="'locations['+i+'][name]'"          :value="loc.name">
            <input type="hidden" :name="'locations['+i+'][lat]'"           :value="loc.lat">
            <input type="hidden" :name="'locations['+i+'][lng]'"           :value="loc.lng">
            <input type="hidden" :name="'locations['+i+'][radius_meters]'" :value="loc.radius_meters">
        </span>
    </template>
    <template x-for="co in countries" :key="'co-'+co"><input type="hidden" name="targeting_rules[countries][]" :value="co"></template>
    <template x-for="r in regions"    :key="'r-'+r"><input type="hidden" name="targeting_rules[regions][]" :value="r"></template>
    <input type="hidden" name="targeting_rules[cities]" :value="JSON.stringify(cities)">
    <input type="hidden" name="targeting_rules[time_start]" :value="timeStart">
    <input type="hidden" name="targeting_rules[time_end]"   :value="timeEnd">

    {{-- Accordion Header --}}
    <div class="px-5 py-4 flex items-center justify-between cursor-pointer select-none transition-colors"
        :class="open ? 'bg-orange-50/60 border-l-[3px] border-l-[#F97316]' : 'hover:bg-gray-50'"
        @click="open = !open">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 transition-colors"
                :class="open ? 'bg-orange-100' : 'bg-gray-100'">
                <svg class="w-4 h-4 transition-colors" :class="open ? 'text-[#F97316]' : 'text-gray-500'"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                </svg>
            </div>
            <span class="text-sm font-semibold text-gray-800">Targeting</span>
        </div>
        <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': open }"
            fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </div>

    {{-- Body --}}
    <div x-show="open" x-collapse>
        <div class="border-t border-gray-100">

            {{-- Targeting Summary --}}
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/40">
                <p class="text-[10px] uppercase tracking-wider font-semibold text-gray-400 mb-3">Targeting Summary</p>
                <div class="space-y-1.5 text-xs">
                    <div class="flex gap-2 items-baseline flex-wrap">
                        <span class="font-semibold text-gray-500 w-28 shrink-0">Demographics</span>
                        <span class="text-gray-400">Gender:</span><span class="text-gray-700" x-text="_fmt(genders,2)"></span>
                        <span class="text-gray-200">·</span>
                        <span class="text-gray-400">Age:</span><span class="text-gray-700" x-text="_fmt(ages,7)"></span>
                        <span class="text-gray-200">·</span>
                        <span class="text-gray-400">Income:</span><span class="text-gray-700" x-text="_fmt(incomes,4)"></span>
                    </div>
                    <div class="flex gap-2 items-baseline flex-wrap">
                        <span class="font-semibold text-gray-500 w-28 shrink-0">Geo</span>
                        <span class="text-gray-700" x-text="summaryGeo()"></span>
                    </div>
                    <div class="flex gap-2 items-baseline flex-wrap">
                        <span class="font-semibold text-gray-500 w-28 shrink-0">Devices</span>
                        <span class="text-gray-400">Devices:</span><span class="text-gray-700" x-text="_fmt(deviceTypes,4)"></span>
                        <span class="text-gray-200">·</span>
                        <span class="text-gray-400">OS:</span><span class="text-gray-700" x-text="_fmt(os,4)"></span>
                    </div>
                    <div class="flex gap-2 items-baseline flex-wrap">
                        <span class="font-semibold text-gray-500 w-28 shrink-0">Schedule</span>
                        <span class="text-gray-400">Days:</span><span class="text-gray-700" x-text="_fmt(days,7)"></span>
                        <template x-if="timeStart || timeEnd">
                            <span class="text-gray-700" x-text="' · ' + (timeStart||'00:00') + '–' + (timeEnd||'24:00')"></span>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Tab Nav --}}
            <div class="flex border-b border-gray-100 bg-white overflow-x-auto">
                @foreach ([
                    ['demographics', 'Demographics'],
                    ['geo', 'Geo &amp; Locations'],
                    ['devices', 'Devices &amp; Tech'],
                    ['inventory', 'Inventory'],
                    ['schedule', 'Schedule'],
                ] as [$tabId, $tabLabel])
                <button type="button"
                    @click="activeTab = '{{ $tabId }}'"
                    :class="activeTab === '{{ $tabId }}'
                        ? 'text-[#F97316] border-b-2 border-[#F97316] font-semibold'
                        : 'text-gray-400 hover:text-gray-700 border-b-2 border-transparent'"
                    class="px-5 py-3 text-xs transition-colors whitespace-nowrap flex-shrink-0">
                    {!! $tabLabel !!}
                </button>
                @endforeach
            </div>

            {{-- Tab Panels --}}
            <div class="p-6" style="min-height:380px">

                {{-- DEMOGRAPHICS --}}
                <div x-show="activeTab === 'demographics'">

                    {{-- Gender --}}
                    <div class="mb-6">
                        <p class="text-[10px] uppercase tracking-wider font-semibold text-gray-400 mb-3">Gender</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach(['Male', 'Female'] as $gender)
                            <button type="button"
                                @click="genders.includes('{{ $gender }}') ? genders = genders.filter(g=>g!=='{{ $gender }}') : genders.push('{{ $gender }}')"
                                :class="genders.includes('{{ $gender }}')
                                    ? 'bg-[#F97316] text-white border-[#F97316]'
                                    : 'bg-white text-gray-500 border-gray-200 hover:border-[#F97316] hover:text-[#F97316]'"
                                class="px-4 py-1.5 text-xs font-semibold rounded-full border transition-colors">
                                {{ $gender }}
                            </button>
                            @endforeach
                            <button type="button"
                                @click="genders = []"
                                :class="genders.length === 0
                                    ? 'bg-[#F97316] text-white border-[#F97316]'
                                    : 'bg-white text-gray-500 border-gray-200 hover:border-[#F97316] hover:text-[#F97316]'"
                                class="px-4 py-1.5 text-xs font-semibold rounded-full border transition-colors">
                                All
                            </button>
                        </div>
                    </div>

                    {{-- Age --}}
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">Age Groups</p>
                            <button type="button" @click="ages = []"
                                class="text-[10px] text-gray-400 hover:text-[#F97316] transition-colors">Clear all</button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach(['18-24','25-34','35-44','45-54','55-64','65+','Unknown'] as $age)
                            <button type="button"
                                @click="ages.includes('{{ $age }}') ? ages = ages.filter(a=>a!=='{{ $age }}') : ages.push('{{ $age }}')"
                                :class="ages.includes('{{ $age }}') || ages.length === 0
                                    ? 'bg-[#F97316] text-white border-[#F97316]'
                                    : 'bg-white text-gray-500 border-gray-200 hover:border-[#F97316] hover:text-[#F97316]'"
                                class="px-3 py-1.5 text-xs font-semibold rounded-full border transition-colors">
                                {{ $age }}
                            </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Income --}}
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">Income</p>
                            <button type="button" @click="incomes = ['0-195K','195-220K','220-245K','245K+']"
                                class="text-[10px] text-gray-400 hover:text-[#F97316] transition-colors">Select all</button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach(['0-195K','195-220K','220-245K','245K+'] as $income)
                            <button type="button"
                                @click="incomes.includes('{{ $income }}') ? incomes = incomes.filter(i=>i!=='{{ $income }}') : incomes.push('{{ $income }}')"
                                :class="incomes.includes('{{ $income }}')
                                    ? 'bg-[#F97316] text-white border-[#F97316]'
                                    : 'bg-white text-gray-500 border-gray-200 hover:border-[#F97316] hover:text-[#F97316]'"
                                class="px-3 py-1.5 text-xs font-semibold rounded-full border transition-colors">
                                {{ $income }}
                            </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- DEVICES & TECH --}}
                <div x-show="activeTab === 'devices'">

                    {{-- Device Types --}}
                    <div class="mb-6">
                        <p class="text-[10px] uppercase tracking-wider font-semibold text-gray-400 mb-3">Device Types</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach(['Mobile','Tablet','Desktop','Connected TV'] as $device)
                            <button type="button"
                                @click="deviceTypes.includes('{{ $device }}') ? deviceTypes = deviceTypes.filter(d=>d!=='{{ $device }}') : deviceTypes.push('{{ $device }}')"
                                :class="deviceTypes.includes('{{ $device }}')
                                    ? 'bg-[#F97316] text-white border-[#F97316]'
                                    : 'bg-white text-gray-500 border-gray-200 hover:border-[#F97316] hover:text-[#F97316]'"
                                class="px-3 py-1.5 text-xs font-semibold rounded-full border transition-colors">
                                {{ $device }}
                            </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Operating System --}}
                    <div class="mb-6">
                        <p class="text-[10px] uppercase tracking-wider font-semibold text-gray-400 mb-3">Operating System</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach(['iOS','Android','Windows','macOS','Other'] as $osName)
                            <button type="button"
                                @click="os.includes('{{ $osName }}') ? os = os.filter(o=>o!=='{{ $osName }}') : os.push('{{ $osName }}')"
                                :class="os.includes('{{ $osName }}')
                                    ? 'bg-[#F97316] text-white border-[#F97316]'
                                    : 'bg-white text-gray-500 border-gray-200 hover:border-[#F97316] hover:text-[#F97316]'"
                                class="px-3 py-1.5 text-xs font-semibold rounded-full border transition-colors">
                                {{ $osName }}
                            </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Connection --}}
                    <div>
                        <p class="text-[10px] uppercase tracking-wider font-semibold text-gray-400 mb-3">Connection Type</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach(['WiFi','Cellular'] as $conn)
                            <button type="button"
                                @click="connectionTypes.includes('{{ $conn }}') ? connectionTypes = connectionTypes.filter(c=>c!=='{{ $conn }}') : connectionTypes.push('{{ $conn }}')"
                                :class="connectionTypes.includes('{{ $conn }}')
                                    ? 'bg-[#F97316] text-white border-[#F97316]'
                                    : 'bg-white text-gray-500 border-gray-200 hover:border-[#F97316] hover:text-[#F97316]'"
                                class="px-3 py-1.5 text-xs font-semibold rounded-full border transition-colors">
                                {{ $conn }}
                            </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- INVENTORY --}}
                <div x-show="activeTab === 'inventory'">
                    <div>
                        <p class="text-[10px] uppercase tracking-wider font-semibold text-gray-400 mb-3">Environments</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach(['In-App','Mobile Web','Desktop Web'] as $env)
                            <button type="button"
                                @click="environments.includes('{{ $env }}') ? environments = environments.filter(e=>e!=='{{ $env }}') : environments.push('{{ $env }}')"
                                :class="environments.includes('{{ $env }}')
                                    ? 'bg-[#F97316] text-white border-[#F97316]'
                                    : 'bg-white text-gray-500 border-gray-200 hover:border-[#F97316] hover:text-[#F97316]'"
                                class="px-3 py-1.5 text-xs font-semibold rounded-full border transition-colors">
                                {{ $env }}
                            </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- SCHEDULE --}}
                <div x-show="activeTab === 'schedule'">

                    {{-- Days --}}
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">Days of Week</p>
                            <button type="button"
                                @click="days.length === 7 ? days = [] : days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']"
                                class="text-[10px] text-gray-400 hover:text-[#F97316] transition-colors"
                                x-text="days.length === 7 ? 'Clear all' : 'Select all'"></button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day)
                            <button type="button"
                                @click="days.includes('{{ $day }}') ? days = days.filter(d=>d!=='{{ $day }}') : days.push('{{ $day }}')"
                                :class="days.includes('{{ $day }}')
                                    ? 'bg-[#F97316] text-white border-[#F97316]'
                                    : 'bg-white text-gray-500 border-gray-200 hover:border-[#F97316] hover:text-[#F97316]'"
                                class="w-14 py-2 text-xs font-bold rounded-xl border transition-colors">
                                {{ $day }}
                            </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Time Range --}}
                    <div>
                        <p class="text-[10px] uppercase tracking-wider font-semibold text-gray-400 mb-3">Time Range</p>
                        <div class="flex items-center gap-4">
                            <div class="flex flex-col gap-1">
                                <label class="text-xs text-gray-600">From</label>
                                <select x-model="timeStart"
                                    class="pl-3 pr-8 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-900 focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/20 transition-all cursor-pointer">
                                    <option value="">—</option>
                                    @for ($h = 0; $h < 24; $h++)
                                        @for ($m = 0; $m < 60; $m += 10)
                                            <option value="{{ sprintf('%02d:%02d', $h, $m) }}">{{ sprintf('%02d:%02d', $h, $m) }}</option>
                                        @endfor
                                    @endfor
                                </select>
                            </div>
                            <span class="text-gray-400 mt-4">—</span>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs text-gray-600">To</label>
                                <select x-model="timeEnd"
                                    class="pl-3 pr-8 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-900 focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/20 transition-all cursor-pointer">
                                    <option value="">—</option>
                                    @for ($h = 0; $h < 24; $h++)
                                        @for ($m = 0; $m < 60; $m += 10)
                                            <option value="{{ sprintf('%02d:%02d', $h, $m) }}">{{ sprintf('%02d:%02d', $h, $m) }}</option>
                                        @endfor
                                    @endfor
                                </select>
                            </div>
                            <button type="button" @click="timeStart=''; timeEnd=''"
                                x-show="timeStart || timeEnd"
                                class="text-xs text-gray-400 hover:text-[#F97316] mt-4 transition-colors">
                                Clear
                            </button>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">Leave empty to run all day</p>
                    </div>
                </div>

                {{-- GEO & LOCATIONS --}}
                <div x-show="activeTab === 'geo'">

                    {{-- BROAD TARGETING --}}
                    <div class="mb-6">
                        <p class="text-[10px] uppercase tracking-wider font-semibold text-gray-400 mb-3">Broad Targeting</p>
                        <div class="grid grid-cols-3 gap-4 p-4 bg-gray-50/40 border border-gray-200 rounded-xl">

                            {{-- Countries --}}
                            <div @click.outside="showCountrySug = false">
                                <div class="flex items-center gap-2 mb-2">
                                    <p class="text-xs font-semibold text-gray-700">Countries</p>
                                    <span x-show="countries.length > 0"
                                        class="text-[10px] font-bold text-[#F97316]"
                                        x-text="countries.length + ' selected'"></span>
                                </div>
                                <div class="relative">
                                    <input type="text" x-model="countryInput"
                                        @focus="showCountrySug = true; if(geoCountriesList.length===0) loadCountriesList()"
                                        @input="showCountrySug = true"
                                        @keydown.enter.prevent="addCountry()"
                                        placeholder="Search countries..."
                                        class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-xs focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/20 transition-all">
                                    <div x-show="showCountrySug && filteredCountrySug.length > 0"
                                        class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden max-h-44 overflow-y-auto">
                                        <template x-for="c in filteredCountrySug" :key="c">
                                            <button type="button" @click="addCountry(c)"
                                                class="w-full text-left px-3 py-2 text-xs text-gray-700 hover:bg-orange-50 hover:text-[#F97316] transition-colors"
                                                x-text="c"></button>
                                        </template>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    <template x-for="(country, idx) in countries" :key="idx">
                                        <span class="inline-flex items-center gap-1 pl-2.5 pr-1 py-0.5 bg-blue-50 border border-blue-200 rounded-full text-xs font-medium text-blue-700">
                                            <span x-text="country"></span>
                                            <button type="button" @click="removeCountry(idx)"
                                                class="w-4 h-4 rounded-full flex items-center justify-center hover:bg-blue-200 text-blue-400 hover:text-blue-600 transition-colors">
                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </span>
                                    </template>
                                </div>
                            </div>

                            {{-- Regions --}}
                            <div @click.outside="showRegionSug = false">
                                <div class="flex items-center gap-2 mb-2">
                                    <p class="text-xs font-semibold text-gray-700">Regions</p>
                                    <span x-show="regions.length > 0"
                                        class="text-[10px] font-bold text-[#F97316]"
                                        x-text="regions.length + ' selected'"></span>
                                </div>
                                <div class="relative" x-show="countries.length > 0">
                                    <input type="text" x-model="regionInput"
                                        @focus="showRegionSug = true"
                                        @input="showRegionSug = true"
                                        @keydown.enter.prevent="addRegion()"
                                        placeholder="Search regions..."
                                        class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-xs focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/20 transition-all">
                                    <div x-show="geoLoadingRegions" class="absolute right-3 top-1/2 -translate-y-1/2">
                                        <svg class="w-3.5 h-3.5 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                    </div>
                                    <div x-show="showRegionSug && filteredRegionSug.length > 0"
                                        class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden max-h-44 overflow-y-auto">
                                        <template x-for="r in filteredRegionSug" :key="r">
                                            <button type="button" @click="addRegion(r)"
                                                class="w-full text-left px-3 py-2 text-xs text-gray-700 hover:bg-orange-50 hover:text-[#F97316] transition-colors"
                                                x-text="r"></button>
                                        </template>
                                    </div>
                                </div>
                                <p x-show="countries.length === 0" class="text-xs text-gray-400 mt-1">Select a country first</p>
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    <template x-for="(region, idx) in regions" :key="idx">
                                        <span class="inline-flex items-center gap-1 pl-2.5 pr-1 py-0.5 bg-purple-50 border border-purple-200 rounded-full text-xs font-medium text-purple-700">
                                            <span x-text="region"></span>
                                            <button type="button" @click="removeRegion(idx)"
                                                class="w-4 h-4 rounded-full flex items-center justify-center hover:bg-purple-200 text-purple-400 hover:text-purple-600 transition-colors">
                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </span>
                                    </template>
                                </div>
                            </div>

                            {{-- Cities --}}
                            <div @click.outside="showCitySug = false">
                                <div class="flex items-center gap-2 mb-2">
                                    <p class="text-xs font-semibold text-gray-700">Cities</p>
                                    <span x-show="cities.length > 0"
                                        class="text-[10px] font-bold text-[#F97316]"
                                        x-text="cities.length + ' selected'"></span>
                                </div>
                                <div class="relative" x-show="countries.length > 0">
                                    <input type="text" x-model="cityInput"
                                        @focus="showCitySug = true"
                                        @input="showCitySug = true"
                                        @keydown.enter.prevent="addCity()"
                                        placeholder="Search cities..."
                                        class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-xs focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/20 transition-all">
                                    <div x-show="geoLoadingCities" class="absolute right-3 top-1/2 -translate-y-1/2">
                                        <svg class="w-3.5 h-3.5 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                    </div>
                                    <div x-show="showCitySug && filteredCitySug.length > 0"
                                        class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden max-h-44 overflow-y-auto">
                                        <template x-for="c in filteredCitySug" :key="c">
                                            <button type="button" @click="addCity(c)"
                                                class="w-full text-left px-3 py-2 text-xs text-gray-700 hover:bg-orange-50 hover:text-[#F97316] transition-colors"
                                                x-text="c"></button>
                                        </template>
                                    </div>
                                </div>
                                <p x-show="countries.length === 0" class="text-xs text-gray-400 mt-1">Select a country first</p>
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    <template x-for="(city, idx) in cities" :key="idx">
                                        <span class="inline-flex items-center gap-1 pl-2.5 pr-1 py-0.5 bg-teal-50 border border-teal-200 rounded-full text-xs font-medium text-teal-700">
                                            <span x-text="city"></span>
                                            <button type="button" @click="removeCity(idx)"
                                                class="w-4 h-4 rounded-full flex items-center justify-center hover:bg-teal-200 text-teal-400 hover:text-teal-600 transition-colors">
                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </span>
                                    </template>
                                </div>
                            </div>

                        </div>
                        <p class="text-xs text-gray-400 mt-2">Type to search and select, or press <kbd class="px-1 py-0.5 bg-gray-100 border border-gray-200 rounded text-[10px] font-mono">Enter</kbd> to add a custom value. Leave all empty to target all.</p>
                    </div>

                    {{-- PROXIMITY TARGETING --}}
                    <div class="border-t border-gray-100 pt-5">
                        <p class="text-[10px] uppercase tracking-wider font-semibold text-gray-400 mb-4">
                            Proximity Targeting <span class="normal-case tracking-normal font-normal text-gray-400">(Points of Interest)</span>
                        </p>

                        <div class="grid grid-cols-2 gap-4">
                            {{-- Left column: AI + Search + Map --}}
                            <div class="flex flex-col gap-3">

                                {{-- AI Location Generator --}}
                                <div>
                                    <button type="button" @click="isAiOpen = !isAiOpen"
                                        class="w-full flex items-center justify-between px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl hover:bg-violet-50/40 hover:border-violet-200 transition-colors group">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-3.5 h-3.5 text-violet-500" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/>
                                            </svg>
                                            <span class="text-sm font-medium text-gray-600 group-hover:text-violet-700 transition-colors">Ask AI to find locations</span>
                                        </div>
                                        <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': isAiOpen }"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>
                                    <div x-show="isAiOpen" x-collapse class="overflow-hidden">
                                        <div class="pt-3 flex gap-2">
                                            <input type="text" x-model="aiPrompt"
                                                placeholder="e.g. Shopping malls in Tel Aviv..."
                                                @keydown.enter.prevent="generateAiLocations()"
                                                class="flex-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-200/40 transition-all">
                                            <button type="button" @click="generateAiLocations()"
                                                :disabled="isAiLoading || aiPrompt.trim() === ''"
                                                class="px-4 py-2 bg-violet-600 hover:bg-violet-700 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-lg text-xs font-semibold transition-colors">
                                                <span x-show="!isAiLoading">Generate</span>
                                                <span x-show="isAiLoading">...</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- Map Search --}}
                                <div class="relative" @click.outside="searchResults = []">
                                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                                    </svg>
                                    <input type="text" x-model="searchQuery"
                                        @input.debounce.400ms="searchPlace()"
                                        placeholder="Search for a place or address..."
                                        class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/20 transition-all">
                                    <div x-show="searchResults.length > 0"
                                        class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden max-h-48 overflow-y-auto">
                                        <template x-for="result in searchResults" :key="result.place_id">
                                            <button type="button" @click="selectResult(result)"
                                                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-[#F97316] transition-colors">
                                                <span class="font-medium" x-text="result.display_name.split(',')[0]"></span>
                                                <span class="text-xs text-gray-400 ml-1" x-text="result.display_name.split(',').slice(1,3).join(',')"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>

                                {{-- Leaflet Map --}}
                                <div id="geo-map" class="w-full rounded-xl overflow-hidden border border-gray-200 flex-1" style="min-height:260px"></div>

                            </div>{{-- end left column --}}

                            {{-- Right column: New Location Form + List --}}
                            <div class="border border-gray-200 rounded-xl p-4 bg-gray-50/40 flex flex-col gap-3">
                                <p class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">New Location</p>

                                <div>
                                    <label class="text-xs text-gray-500 mb-1.5 block">Location Name <span class="text-gray-400">(optional)</span></label>
                                    <input type="text" x-model="newLocation.name"
                                        placeholder="e.g. Tel Aviv City Center"
                                        class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/20 transition-all">
                                </div>

                                <p class="text-xs text-gray-400">Search or click the map to set a pin.</p>

                                <div class="flex items-center gap-2">
                                    <button type="button" @click="addLocation()"
                                        :disabled="newLocation.lat === ''"
                                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#F97316] hover:bg-orange-600 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-lg text-sm font-semibold transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        Add
                                    </button>
                                    <template x-if="newLocation.lat !== ''">
                                        <span class="text-[10px] text-gray-400"
                                            x-text="parseFloat(newLocation.lat).toFixed(4) + ', ' + parseFloat(newLocation.lng).toFixed(4)"></span>
                                    </template>
                                </div>

                                {{-- Locations List --}}
                                <div x-show="locations.length === 0" class="flex-1 flex items-center justify-center py-4">
                                    <p class="text-xs text-gray-400 italic">No locations added yet.</p>
                                </div>

                                <div x-show="locations.length > 0" class="flex-1 overflow-y-auto space-y-2" style="max-height:160px">
                                    <template x-for="(loc, idx) in locations" :key="idx">
                                        <div class="flex items-center gap-2 p-2.5 bg-white border border-gray-200 rounded-lg">
                                            <div class="w-6 h-6 rounded-full bg-blue-50 flex items-center justify-center flex-shrink-0">
                                                <svg class="w-3 h-3 text-blue-500" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                                </svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-semibold text-gray-700 truncate" x-text="loc.name || 'Unnamed'"></p>
                                                <p class="text-[10px] text-gray-400" x-text="Math.round((loc.radius_meters||1000)/1000) + ' km radius'"></p>
                                            </div>
                                            <div class="flex items-center gap-1 flex-shrink-0">
                                                <button type="button" @click="openEdit(idx)"
                                                    class="text-[10px] text-gray-400 hover:text-[#F97316] transition-colors px-1">Edit</button>
                                                <button type="button" @click="removeLocation(idx)"
                                                    class="w-5 h-5 rounded-full flex items-center justify-center hover:bg-red-100 text-gray-300 hover:text-red-500 transition-colors">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Edit Location Modal --}}
                    <template x-teleport="body">
                        <div x-show="showEditModal" x-cloak
                            class="fixed inset-0 bg-black/40 flex items-center justify-center p-4"
                            style="z-index:9020"
                            @click.self="closeEdit()"
                            @keydown.window.escape="closeEdit()">
                            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm border border-gray-100 p-6">
                                <h3 class="text-sm font-semibold text-gray-800 mb-4">Edit Location</h3>
                                <div class="flex flex-col gap-4">
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">Name</label>
                                        <input type="text" x-model="editingLocation.name"
                                            class="px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/20 transition-all">
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">Latitude</label>
                                            <input type="number" x-model="editingLocation.lat" step="0.000001"
                                                class="px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/20 transition-all">
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">Longitude</label>
                                            <input type="number" x-model="editingLocation.lng" step="0.000001"
                                                class="px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/20 transition-all">
                                        </div>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">Radius (km)</label>
                                        <input type="number" x-model="editingLocation.radius_km" min="1" max="100"
                                            class="px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/20 transition-all">
                                    </div>
                                </div>
                                <div class="flex items-center justify-between mt-6">
                                    <button type="button" x-show="editingIndex !== null" @click="deleteEdit()"
                                        class="text-xs text-red-500 hover:text-red-700 transition-colors">Delete location</button>
                                    <div class="flex gap-2 ml-auto">
                                        <button type="button" @click="closeEdit()"
                                            class="px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-all">
                                            Cancel
                                        </button>
                                        <button type="button" @click="saveEdit()"
                                            class="px-4 py-2 text-sm font-bold text-white bg-[#F97316] hover:bg-orange-600 rounded-xl transition-all">
                                            Save
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                </div>{{-- end geo tab --}}

            </div>{{-- end tab panels --}}
        </div>{{-- end border-t --}}
    </div>{{-- end x-collapse --}}
</div>
