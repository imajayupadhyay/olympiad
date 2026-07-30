/**
 * National Excellence Olympiad — syllabus reference data.
 *
 * Source: "National Olympiad Syllabus (Class 1–10)" PDF.
 *
 * Two places where the source PDF is incomplete and the content was carried
 * over from the earlier syllabus handbook (same document family) so no class
 * shows an empty panel — see `hindi` below, marked inline.
 *
 * Static for now. `Pages/Public/Syllabus/Index.vue` accepts an optional
 * `syllabus` prop and falls back to this module, so moving the content into
 * the admin panel later only means passing the same shape from the controller.
 *
 * Authoring shape (kept compact on purpose — it is meant to be edited by hand):
 *   subject.topics[class] = 'Comma, separated, topics'            → one strand
 *   subject.topics[class] = { 'Strand name': 'Comma, separated' } → named strands
 */

export const CLASSES = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

export const SUBJECTS = [
    {
        key: 'english',
        label: 'English',
        short: 'Eng',
        color: '#2C49A6',
        blurb: 'Four strands in every paper — grammar, vocabulary, comprehension and writing.',
        topics: {
            1: {
                Grammar: 'Nouns, Verbs, Pronouns, Singular-Plural, Articles (a, an), Prepositions (in, on, under, etc.), Capitalization, Punctuation',
                Vocabulary: 'Opposites, Rhyming Words, Picture Vocabulary',
                'Reading Comprehension': 'Picture-based comprehension, Short passages',
                'Writing & Communication': 'Greetings, Simple Sentence Formation, Picture Description',
            },
            2: {
                Grammar: 'Nouns, Pronouns, Verbs, Adjectives, Articles, Prepositions, Simple Present Tense, Punctuation',
                Vocabulary: 'Basic Synonyms, Antonyms, Compound Words, Rhyming Words',
                'Reading Comprehension': 'Short stories, Sequencing Events',
                'Writing & Communication': 'Sentence Completion, Short Message Writing, Everyday Communication',
            },
            3: {
                Grammar: 'Common and Proper Nouns, Pronouns, Verbs, Adjectives, Adverbs, Articles, Conjunctions, Subject-Verb Agreement, Simple Tenses',
                Vocabulary: 'Synonyms, Antonyms, Homophones, Compound Words',
                'Reading Comprehension': 'Factual and Story-based Passages',
                'Writing & Communication': 'Paragraph Writing, Picture Composition, Informal Letter (Basic)',
            },
            4: {
                Grammar: 'Collective Nouns, Adverbs, Conjunctions, Interjections, Prepositions, Degrees of Comparison, Tenses, Subject-Verb Agreement',
                Vocabulary: 'Homonyms, Prefixes, Suffixes, Basic Idioms',
                'Reading Comprehension': 'Informative Passages, Character Analysis',
                'Writing & Communication': 'Paragraph Writing, Informal Messages, Invitations',
            },
            5: {
                Grammar: 'Parts of Speech, Tenses, Modals, Conjunctions, Prepositions, Subject-Verb Agreement, Active Voice (Basic)',
                Vocabulary: 'Idioms and Phrases, Synonyms, Antonyms, Prefixes and Suffixes',
                'Reading Comprehension': 'Informational Passages, Vocabulary in Context',
                'Writing & Communication': 'Message Writing, Story Completion, Diary Entry',
            },
            6: {
                Grammar: 'Tenses, Modals, Determiners, Active-Passive Voice (Introduction), Direct-Indirect Speech (Introduction), Clauses and Phrases',
                Vocabulary: 'One-word Substitutions, Idioms and Phrases, Collocations',
                'Reading Comprehension': 'Descriptive and Data-based Passages',
                'Writing & Communication': 'Notice Writing, Email Writing, Diary Entry',
            },
            7: {
                Grammar: 'Tenses, Voice, Narration, Non-finite Verbs, Determiners, Subject-Verb Agreement, Error Detection',
                Vocabulary: 'Phrasal Verbs, Idioms, Word Roots',
                'Reading Comprehension': 'Discursive Passages, Inferential Comprehension',
                'Writing & Communication': 'Formal and Informal Emails, Notices, Diary Entry, Short Speeches',
            },
            8: {
                Grammar: 'Voice, Narration, Clauses, Sentence Transformation, Editing and Omission, Modals',
                Vocabulary: 'Advanced Idioms, One-word Substitutions, Contextual Vocabulary',
                'Reading Comprehension': 'Analytical and Case-based Passages',
                'Writing & Communication': 'Article Writing, Descriptive Writing, Narrative Writing',
            },
            9: {
                Grammar: 'Advanced Tenses, Voice, Narration, Clauses, Sentence Transformation, Error Spotting',
                Vocabulary: 'Phrasal Verbs, Idioms, Confusing Words',
                'Reading Comprehension': 'Analytical and Critical Reading Passages',
                'Writing & Communication': 'Formal Emails, Articles, Speeches, Debate Writing',
            },
            10: {
                Grammar: 'Voice, Narration, Editing, Omission, Sentence Reordering, Gap Filling',
                Vocabulary: 'Advanced Vocabulary, Idioms, One-word Substitutions',
                'Reading Comprehension': 'Case-based and Critical Comprehension',
                'Writing & Communication': 'Reports, Articles, Speeches, Formal Correspondence',
            },
        },
    },
    {
        key: 'hindi',
        label: 'Hindi',
        short: 'हिं',
        color: '#7A4A2B',
        script: 'devanagari',
        blurb: 'व्याकरण, शब्द-भंडार और अपठित गद्यांश — कक्षा के स्तर के अनुसार।',
        topics: {
            1: 'भाषा, वर्णमाला, स्वर और व्यंजन, मात्राएँ, शब्द और वाक्य, संज्ञा, लिंग, वचन, सर्वनाम, विशेषण, क्रिया, समानार्थी शब्द, विलोम शब्द, गिनती',
            2: 'हमारी भाषा, मात्राओं का अभ्यास, शब्द और वाक्य, संज्ञा, लिंग, वचन, सर्वनाम, विशेषण, क्रिया, समानार्थी शब्द, विलोम शब्द, अनेक शब्दों के लिए एक शब्द, विराम चिह्न, गिनती, दिन, सप्ताह और महीने',
            3: 'भाषा, व्याकरण और लिपि, वर्णमाला, मात्राएँ, शब्द और वाक्य, संज्ञा, लिंग, वचन, सर्वनाम, विशेषण, क्रिया, समानार्थी शब्द, विलोम शब्द, अनेक शब्दों के लिए एक शब्द, अनेकार्थी शब्द, विराम चिह्न, उपसर्ग और प्रत्यय, मुहावरे, अपठित गद्यांश, संख्यावाची शब्द, अंकों को शब्दों में लिखना एवं शब्दों को अंकों में लिखना, दिन, महीने और त्योहार',
            4: 'भाषा, व्याकरण और लिपि, वर्णमाला, मात्राएँ, शब्द और वाक्य, संज्ञा, लिंग, वचन, सर्वनाम, विशेषण, क्रिया, वर्तनी शुद्धि, पर्यायवाची शब्द, विलोम शब्द, अनेक शब्दों के लिए एक शब्द, अनेकार्थी शब्द, विराम चिह्न, उपसर्ग और प्रत्यय, मुहावरे, लोकोक्तियाँ, अपठित गद्यांश, संख्यावाची शब्द, अंकों को शब्दों में लिखना एवं शब्दों को अंकों में लिखना',
            5: 'भाषा, लिपि, व्याकरण, वर्ण विचार, शब्द रचना, उपसर्ग और प्रत्यय, वाक्य, संज्ञा, लिंग, वचन, कारक, सर्वनाम, विशेषण, क्रिया, काल, अव्यय, पर्यायवाची शब्द, विलोम शब्द, अनेक शब्दों के लिए एक शब्द, अनेकार्थी शब्द, श्रुतिसमभिन्नार्थक शब्द, शब्द-युग्म, वर्तनी शुद्धि, विराम चिह्न, मुहावरे और लोकोक्तियाँ, अपठित गद्यांश',
            6: 'भाषा, लिपि, व्याकरण, वर्ण विचार, शब्द रचना, उपसर्ग और प्रत्यय, वाक्य विचार, संज्ञा, लिंग, वचन, कारक, सर्वनाम, विशेषण, क्रिया, काल, अव्यय, पर्यायवाची शब्द, विलोम शब्द, अनेक शब्दों के लिए एक शब्द, अनेकार्थी शब्द, श्रुतिसमभिन्नार्थक शब्द, शब्द-युग्म, वर्तनी शुद्धि, विराम चिह्न, मुहावरे और लोकोक्तियाँ, अपठित गद्यांश',
            // PDF truncates Class 7 mid-phrase at "मुहावरे और" and has no Classes 8–10.
            // Completed from the earlier syllabus handbook — verify against the full source.
            7: 'भाषा, लिपि, व्याकरण, वर्ण विचार, संधि, शब्द विचार, पर्यायवाची शब्द, विलोम शब्द, अनेक शब्दों के लिए एक शब्द, अनेकार्थी शब्द, श्रुतिसमभिन्नार्थक शब्द, उपसर्ग, प्रत्यय, समास, संज्ञा, लिंग, वचन, कारक, सर्वनाम, विशेषण, क्रिया, काल, अव्यय, संबंधबोधक, समुच्चयबोधक, विस्मयादिबोधक, वाक्य विचार, वर्तनी शुद्धि, विराम चिह्न, मुहावरे और लोकोक्तियाँ, अपठित गद्यांश, अपठित पद्यांश',
            8: 'भाषा, लिपि, बोली और व्याकरण, वर्ण विचार, शब्द विचार एवं शब्द भेद, संधि, उपसर्ग और प्रत्यय, समास, संज्ञा, लिंग, वचन, कारक, सर्वनाम, विशेषण, क्रिया, काल, वाच्य, अव्यय, क्रियाविशेषण, संबंधबोधक, समुच्चयबोधक, विस्मयादिबोधक, पर्यायवाची शब्द, विलोम शब्द, अनेक शब्दों के लिए एक शब्द, अनेकार्थी शब्द, श्रुतिसमभिन्नार्थक शब्द, वाक्य रचना, वर्तनी शुद्धि, विराम चिह्न, अलंकार, मुहावरे और लोकोक्तियाँ, अपठित गद्यांश, अपठित पद्यांश',
            9: 'भाषा, लिपि, बोली और व्याकरण, वर्ण विचार, शब्द विचार एवं शब्द भेद, संधि, उपसर्ग और प्रत्यय, समास, संज्ञा, लिंग, वचन, कारक, सर्वनाम, विशेषण, क्रिया, काल, वाच्य, अव्यय, क्रियाविशेषण, संबंधबोधक, समुच्चयबोधक, विस्मयादिबोधक, पर्यायवाची शब्द, विलोम शब्द, अनेक शब्दों के लिए एक शब्द, अनेकार्थी शब्द, श्रुतिसमभिन्नार्थक शब्द, वाक्य रचना, वर्तनी शुद्धि, विराम चिह्न, अलंकार, मुहावरे और लोकोक्तियाँ, अपठित गद्यांश, अपठित पद्यांश',
            10: 'भाषा, लिपि, बोली और व्याकरण, वर्ण विचार, शब्द विचार एवं शब्द भेद, संधि, उपसर्ग और प्रत्यय, समास, संज्ञा, लिंग, वचन, कारक, सर्वनाम, विशेषण, क्रिया, काल, वाच्य, अव्यय, क्रियाविशेषण, संबंधबोधक, समुच्चयबोधक, विस्मयादिबोधक, पर्यायवाची शब्द, विलोम शब्द, अनेक शब्दों के लिए एक शब्द, अनेकार्थी शब्द, श्रुतिसमभिन्नार्थक शब्द, वाक्य रचना, वर्तनी शुद्धि, विराम चिह्न, अलंकार, मुहावरे और लोकोक्तियाँ, अपठित गद्यांश, अपठित पद्यांश',
        },
    },
    {
        key: 'maths',
        label: 'Mathematics',
        short: 'Math',
        color: '#A8761A',
        blurb: 'Number sense to trigonometry, with reasoning and real-life application woven through every level.',
        topics: {
            1: 'Numerals, Number Names, Number Sense (up to 99), Place Value, Addition, Subtraction, Shapes and Solids, Measurement of Length and Weight, Time, Money, Patterns, Pictographs, Matching, Odd One Out, Spatial Understanding',
            2: 'Numerals, Number Names, Number Sense (up to 1000), Addition, Subtraction, Multiplication, Division, Skip Counting, Measurement, Capacity, Temperature, Time, Money, Lines, Shapes and Solids, Pictographs, Calendars, Classification, Figure Completion, Coding Patterns',
            3: 'Numerals, Number Names, Number Sense (up to 9999), Addition, Subtraction, Multiplication, Division, Fractions, Measurement, Time, Money, Symmetry, Introduction to Angles, Perimeter, Data Collection, Bar Graphs, Timetables, Analogies, Figure Matrix, Logical Sequences',
            4: 'Large Numbers, Factors and Multiples, Fractions, Introduction to Decimals, Addition, Subtraction, Multiplication and Division, Measurement, Angles, Symmetry, Perimeter and Area, Unit Conversions, Data Representation, Tables and Graphs, Estimation, Pattern Recognition, Problem Solving',
            5: 'Decimals, Fractions, Factors and Multiples, Operations on Whole Numbers and Decimals, Measurement, Angles, Circles, Perimeter, Area, Introduction to Volume, Symmetry, Data Handling, Maps and Floor Plans, Mathematical Puzzles, Logical Sequences',
            6: 'Integers, Fractions, Decimals, Ratio and Proportion, Algebraic Expressions, Lines and Angles, Triangles, Constructions, Symmetry, Data Handling, Statistics, Speed-Distance-Time, Bills and Receipts, Verbal and Non-Verbal Reasoning',
            7: 'Rational Numbers, Exponents and Powers, Algebraic Expressions, Simple Equations, Congruence, Practical Geometry, Data Interpretation, Introduction to Probability, Pattern Recognition, Analytical Reasoning, Verbal and Non-Verbal Reasoning',
            8: 'Exponents and Powers, Linear Equations, Direct and Inverse Proportion, Quadrilaterals, Mensuration, Coordinate Geometry, Graphs, Statistics, Mathematical Modelling, Real-life Applications of Mathematics, Critical Thinking, Verbal and Non-Verbal Reasoning',
            9: 'Number Systems, Polynomials, Coordinate Geometry, Linear Equations in Two Variables, Triangles, Circles, Surface Areas and Volumes, Statistics, Probability, Data Interpretation, Quantitative Reasoning, Assertion-Reason Questions, Problem Solving',
            10: 'Real Numbers, Polynomials, Quadratic Equations, Arithmetic Progressions, Coordinate Geometry, Trigonometry, Applications of Trigonometry, Circles, Surface Areas and Volumes, Statistics, Probability, Data Interpretation, Logical Reasoning, Application-based Questions, Higher Order Thinking Skills (HOTS)',
        },
    },
    {
        key: 'science',
        label: 'Science',
        short: 'Sci',
        color: '#168A66',
        blurb: 'The living world, matter and energy, and the earth in space — built up class by class.',
        topics: {
            1: 'Living and Non-Living Things, Plants Around Us, Animals Around Us, Our Body and Healthy Habits, Safety Rules, Air and Water, Weather and Seasons',
            2: 'Plants and Their Uses, Animals and Their Habitats, Food and Nutrition, Clothes and Shelter, Good Habits and Safety, Means of Transport and Communication, Earth and Sky',
            3: 'Plants and Animals, Birds Around Us, Human Body, Food and Occupation, Transport and Communication, Matter Around Us, Light, Sound and Force, Caring for the Environment, Earth and Space',
            4: 'Plant Life, Animal Life, Food and Digestion, Human Needs, Materials and Their Properties, Force and Energy, Environment Protection, Earth and the Solar System',
            5: 'Human Body and Health, Plant Functions, Natural Resources, Pollution and Natural Disasters, Matter and Materials, Force and Energy, Earth and Space Science',
            6: 'Science in Everyday Life, Components of Food, Materials and Their Separation, Changes Around Us, Diversity of Living Organisms, Magnets and Magnetism, Measurement and Motion, Temperature, Natural Resources, Space and Celestial Bodies',
            7: 'Nature of Science, Functions of Plants and Animals, Acids, Bases and Salts, Metals and Non-Metals, Heat and Its Transfer, Physical and Chemical Changes, Motion and Time, Electricity and Circuits, Light, Earth, Moon and Sun',
            8: 'Scientific Methods and Discoveries, Microorganisms, Health and Diseases, Matter and Its Structure, Elements and Compounds, Force and Pressure, Solutions, Electricity, Light, Weather and Climate, Astronomy',
            9: 'Motion, Force and Laws of Motion, Gravitation, Work and Energy, Sound, Matter in Different States, Atoms and Molecules, Structure of the Atom, Cell and Tissues, Natural Resources, Food Production and Management',
            10: 'Chemical Reactions, Acids, Bases and Salts, Metals and Non-Metals, Carbon Compounds, Life Processes, Coordination in Living Organisms, Reproduction and Heredity, Light and Vision, Electricity and Magnetism, Environmental Conservation',
        },
    },
    {
        key: 'social',
        label: 'Social Studies',
        short: 'SST',
        color: '#9B2C4E',
        blurb: 'History, geography and civics, always anchored to maps, sources and current events.',
        topics: {
            1: 'Myself and My Family, My School and Community, Good Habits and Safety Rules, Festivals and Celebrations, National Symbols, Means of Transport and Communication, Our Earth, Air and Water, Seasons and Weather, Maps and Directions (Introduction), People Who Help Us, Current Events',
            2: 'Family and Relationships, Housing and Clothing, Festivals and Traditions, Community Helpers, Means of Transport and Communication, India and Its Diversity, Our Environment, Earth and Sky, Maps and Directions, Famous Personalities, Current Events',
            3: 'Basic Needs, Festivals and Celebrations, People Who Help Us, The Solar System and Our Earth, Environment Conservation, India – Our Motherland, India – Our Heritage, Early Civilizations, Transport and Communication, Maps and Directions, Current Events',
            4: 'India – Physical and Political Features, Climate and Natural Resources, Indian Heritage and Culture, Great Leaders and Reformers, Constitution and Government (Introduction), Our History, Environment Conservation, Transport and Communication, Map Skills, Current Events',
            5: 'The Earth and Its Environment, Weather and Climate, India – Unity in Diversity, Freedom Movement (Introduction), Great Achievers, International Organizations (Introduction), Agriculture and Industries, Inventions and Discoveries, Evolution of Human Society, Transport and Communication, Current Events',
            6: 'The Earth and the Solar System, Continents and Oceans, Landforms, Ancient Civilizations, Early Indian History, Indian Culture and Heritage, Diversity in India, Family and Community, Government and Administration, Economic Activities, Maps and Data Interpretation, Current Events',
            7: 'Medieval Indian History, Kingdoms and Empires, Bhakti and Sufi Traditions, Geography of India and the World, Natural Resources, Democracy and Equality, State Government, Media and Society, Markets Around Us, Map Skills, Source-based Questions, Current Events',
            8: 'Modern Indian History, Colonial Rule and Resistance, The Revolt of 1857, Freedom Struggle, Natural Resources, Agriculture and Industries, Indian Constitution, Parliament and Judiciary, Social Justice, Economic Activities, Maps and Data Interpretation, Current Events',
            9: 'The French Revolution, Socialism and Nazism, Forest Society and Colonialism, India’s Physical Features, Climate, Population, Democratic Rights, Electoral Politics, Working of Institutions, Rural Economy, Poverty and Food Security, Map Work, Case-based Questions, Current Events',
            10: 'Nationalism in Europe and India, Print Culture and the Modern World, Globalisation, Resources and Development, Agriculture and Industries, Water and Forest Resources, Federalism, Political Parties, Democracy and Diversity, Sectors of the Indian Economy, Consumer Rights, Map Work, Source-based Questions, Current Events',
        },
    },
    {
        key: 'cs',
        label: 'Computer Science',
        short: 'CS',
        color: '#0E7490',
        blurb: 'From parts of a computer to Python and web design, with cyber safety at every level.',
        topics: {
            1: 'Introduction to Computers, Parts of a Computer, Uses of Computers, Computer Lab Etiquette, Keyboard and Mouse, Typing Letters, Numbers and Symbols, Introduction to MS Paint, Everyday Uses of Technology',
            2: 'Types of Computers, Input and Output Devices, Storage Devices, Working of a Computer (Input–Process–Output Cycle), Keyboard Shortcuts (Basic), MS Paint, WordPad, Introduction to MS Word, Safe Use of Computers',
            3: 'Hardware and Software, Operating System (Introduction), Files and Folders, Windows Environment, MS Word (Basic), Internet (Introduction), Computational Thinking (Patterns and Sequencing), Introduction to Artificial Intelligence, AI Around Us',
            4: 'Evolution of Computers, Memory and Storage Devices, Input and Output Devices, Operating System Functions, MS Word (Formatting and Editing), MS PowerPoint (Introduction), Internet and Search Engines, Digital Citizenship, Pattern Recognition, AI in Daily Life',
            5: 'Generations of Computers, Computer Languages (Introduction), MS Word, MS Excel (Basic Worksheets, Rows, Columns and Simple Formulas), MS PowerPoint, Internet and E-mail, Cloud Storage (Introduction), Cyber Safety, AI Applications, Data for AI',
            6: 'Computer Architecture (Basic), Algorithms and Flowcharts, Visual Programming (Scratch/Blockly Concepts), MS Word (Mail Merge), MS Excel (Functions and Charts), MS PowerPoint (Animations and Transitions), Internet Services, Machine Learning (Introduction), Chatbots',
            7: 'Operating Systems, Number System (Binary), Programming Fundamentals, Database Concepts, Computer Networks, MS Excel (Advanced Functions), Computer Security and Malware, Computer Vision, AI Ethics',
            8: 'Data Representation, Memory Management, HTML, CSS, Computer Networks, Cloud Computing, Cyber Security, Generative AI (Introduction), Natural Language Processing (Basics)',
            9: 'Computer System Organization, Software Classification, Networking, Multimedia, MS Word, MS Excel, MS PowerPoint (Advanced Features), Python (Variables, Data Types, Operators, Input/Output, Conditional Statements, Loops, Functions, Lists), Cyber Safety, AI Lifecycle, Prompt Engineering (Basics)',
            10: 'Internet Services, HTML and CSS, Web Designing, Tables, Forms and Multimedia in HTML, Python (Functions, Strings, Lists, Dictionaries, File Handling), Database Management (Introduction), Cyber Ethics, Intellectual Property Rights, E-commerce, Generative AI Tools, Responsible AI',
        },
    },
    {
        key: 'gk',
        label: 'General Knowledge',
        short: 'GK',
        color: '#6C3FA0',
        blurb: 'The world beyond the textbook, closing each year with the events that shaped it.',
        topics: {
            1: 'Me and My Family, My School and Surroundings, Good Habits, Plants and Animals, Festivals and Celebrations, India and National Symbols, Science Around Us, Sports and Games, Language and Literature, Simple Current Events',
            2: 'Community Helpers, Transport and Communication, Plants and Animals, Earth and Environment, India and the World, Science and Technology, Sports, Famous Stories and Characters, Moral Values, Recent Events',
            3: 'Environment Conservation, India and the World, Science Discoveries, Famous Personalities, Transport and Communication, Sports, Language and Literature, Maps and Directions, Current Events',
            4: 'Human Body and Health, Environment and Conservation, Science and Technology, Indian States and Capitals, Famous Monuments, Entertainment, Sports, Literature, Recent Developments',
            5: 'Human Body Systems, Space and Astronomy, Sustainability, India and the World, Arts and Culture, Scientific Innovations, Sports, Literature, Current Events',
            6: 'Plants and Animals, Universe and Space Exploration, Science and Technology, Social Studies, Indian Heritage, Environment, Sports, Media Awareness, Communication Skills, Recent National and International Events',
            7: 'Biodiversity, Universe, Indian History, Civics, Geography, Scientific Innovations, Sports, Literature and Media, Current Affairs',
            8: 'Climate Change, Indian Constitution, World Geography, Space Missions, Emerging Technologies, International Organizations, Sports, Literature and Media, Current Affairs',
            9: 'Environment and Sustainability, Emerging Technologies, Artificial Intelligence Basics, Indian Economy and Governance, Social Sciences, Media Literacy, Sports, Communication Skills, Current Affairs',
            10: 'Artificial Intelligence and Robotics, Entrepreneurship and Innovation, International Relations, Space Science, Indian Economy and Governance, Environmental Challenges, Media Awareness, Sports, Career Readiness, Current Affairs',
        },
    },
    {
        key: 'reasoning',
        label: 'Logical Reasoning & Aptitude',
        short: 'LR',
        color: '#C9501A',
        blurb: 'Patterns, puzzles and quantitative aptitude — the thinking paper that sits alongside the subjects.',
        topics: {
            1: 'Patterns, Analogy, Classification, Grouping, Odd One Out, Spatial Understanding, Shapes and Figures, Position and Direction, Ranking, Measurement, Money, Time, Calendar, Observation Skills, Visual Reasoning',
            2: 'Analogy, Coding-Decoding, Classification and Grouping, Odd One Out, Ranking, Patterns, Geometrical Shapes, Embedded Figures, Money, Time, Calendar, Measurement',
            3: 'Analogy (Verbal and Numerical), Odd One Out (Verbal and Numerical), Alphabetical and Dictionary Order, Missing Letters and Numbers, Coding-Decoding, Mathematical Operations, Symbols, Sitting Arrangement, Blood Relations, Direction Sense, Ranking and Ordering, Missing Numbers, Mirror and Water Images, Figure Sequencing, Figure Completion, Embedded and Overlapping Figures, Number System, Algebra, Geometry, Mensuration, Data Handling',
            4: 'Analogy (Verbal and Numerical), Odd One Out (Verbal and Numerical), Alphabetical and Dictionary Order, Missing Letters and Numbers, Coding-Decoding, Mathematical Operations, Symbols, Sitting Arrangement, Blood Relations, Direction Sense, Ranking and Ordering, Number Series, Missing Numbers, Mirror and Water Images, Figure Sequencing, Figure Completion, Embedded Figures, Paper Folding and Cutting, Number System, Algebra, Geometry, Mensuration, Data Handling',
            5: 'Analogy (Verbal and Numerical), Odd One Out (Verbal and Numerical), Alphabetical and Dictionary Order, Missing Letters and Numbers, Coding-Decoding, Mathematical Operations, Symbols, Sitting Arrangement, Blood Relations, Direction Sense, Ranking and Ordering, Number Series, Missing Numbers, Logical Puzzles, Mirror and Water Images, Figure Sequencing, Figure Completion, Embedded Figures, Paper Folding and Cutting, Cubes and Dice, Number System, Algebra, Geometry, Mensuration, Data Handling',
            6: 'Analogy (Verbal and Numerical), Odd One Out (Verbal and Numerical), Alphabetical and Dictionary Order, Missing Letters and Numbers, Coding-Decoding, Mathematical Operations, Symbols, Sitting Arrangement, Blood Relations, Direction Sense, Ranking and Ordering, Number Series, Missing Numbers, Clock and Calendar, Mirror and Water Images, Figure Sequencing, Figure Completion, Embedded Figures, Paper Folding and Cutting, Cubes and Dice, Number System, Algebra, Geometry, Mensuration, Data Handling',
            7: 'Analogy (Verbal and Numerical), Odd One Out (Verbal and Numerical), Alphabetical and Dictionary Order, Missing Letters and Numbers, Coding-Decoding, Mathematical Operations, Symbols, Sitting Arrangement, Blood Relations, Direction Sense, Ranking and Ordering, Number Series, Missing Numbers, Clock and Calendar, Cubes and Dice, Mirror and Water Images, Figure Sequencing, Figure Completion, Embedded Figures, Paper Folding and Cutting, Logical Puzzles, Number System, Algebra, Geometry, Mensuration, Data Handling',
            8: 'Analogy (Verbal and Numerical), Odd One Out (Verbal and Numerical), Alphabetical and Dictionary Order, Missing Letters and Numbers, Coding-Decoding, Mathematical Operations, Symbols, Sitting Arrangement, Blood Relations, Direction Sense, Ranking and Ordering, Number Series, Missing Numbers, Statement Analysis, Statement and Conclusion, Clock and Calendar, Cubes and Dice, Mirror and Water Images, Figure Sequencing, Figure Completion, Embedded Figures, Paper Folding and Cutting, Rule Detection, Number System, Algebra, Geometry, Mensuration, Data Handling',
            9: 'Analogy (Verbal and Numerical), Odd One Out (Verbal and Numerical), Alphabetical and Dictionary Order, Missing Letters and Characters, Ranking and Ordering, Coding-Decoding, Mathematical Operations, Symbols, Sitting Arrangement, Blood Relations, Direction Sense, Statement and Argument, Syllogism, Data Interpretation, Mirror and Water Images, Figure Sequencing, Figure Completion, Embedded Figures, Paper Folding and Cutting, English Grammar, Number System, Algebra, Geometry, Mensuration, Permutations and Combinations, Probability',
            10: 'Analogy (Verbal and Numerical), Odd One Out (Verbal and Numerical), Ranking and Ordering, Missing Characters, Coding-Decoding, Mathematical Operations, Symbols, Sitting Arrangement, Blood Relations, Direction Sense, Statement and Argument, Statement and Assumption, Statement and Courses of Action, Verification of Statements, Situation Reaction Test, Syllogism, Mirror and Water Images, Figure Completion, Embedded Figures, Paper Folding and Cutting, Dot Situation, English Grammar, Number System, Algebra, Geometry, Mensuration, Data Interpretation, Permutations and Combinations, Probability',
        },
    },
];

/**
 * Split a topic list on commas that separate topics — not on the commas inside
 * a topic's own brackets. "Articles (a, an), Nouns" is two topics, not three.
 */
function split(value) {
    const topics = [];
    let depth = 0;
    let current = '';

    for (const char of String(value)) {
        if (char === '(' || char === '[') depth += 1;
        else if (char === ')' || char === ']') depth = Math.max(0, depth - 1);

        if (char === ',' && depth === 0) {
            topics.push(current);
            current = '';
            continue;
        }
        current += char;
    }
    topics.push(current);

    return topics.map((t) => t.trim()).filter(Boolean);
}

/**
 * Turn either authoring shape into the render shape:
 *   [{ strand: 'Grammar', topics: ['Nouns', 'Verbs'] }]
 * A plain string becomes a single unnamed strand.
 */
export function toStrands(entry) {
    if (!entry) return [];
    if (typeof entry === 'string') return [{ strand: null, topics: split(entry) }];

    return Object.entries(entry).map(([strand, topics]) => ({ strand, topics: split(topics) }));
}

export function countTopics(entry) {
    return toStrands(entry).reduce((sum, s) => sum + s.topics.length, 0);
}
